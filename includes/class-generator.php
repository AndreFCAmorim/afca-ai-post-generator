<?php
/**
 * AIPG_Generator
 *
 * Orchestrates post creation:
 *  1. Picks a topic from the configured list
 *  2. Builds a prompt that includes all requirements
 *  3. Calls Gemini
 *  4. Parses the structured response
 *  5. Inserts a post with status 'pending' for editorial review
 */

defined( 'ABSPATH' ) || exit;

class AIPG_Generator {

	/**
	 * Run a generation cycle. Called by the cron hook.
	 * Generates up to `posts_per_run` posts.
	 *
	 * @return array Results log entries
	 */
	public static function run(): array {
		$results = [];
		$count   = AIPG_Settings::get_posts_per_run();

		for ( $i = 0; $i < $count; $i++ ) {
			$results[] = self::generate_one();
			// Small pause between API calls when generating multiple posts
			if ( $i < $count - 1 ) {
				sleep( 2 );
			}
		}

		return $results;
	}

	/**
	 * Generate a single post and insert it into WordPress.
	 *
	 * @return array { success: bool, message: string, post_id?: int, topic?: string }
	 */
	public static function generate_one(): array {
		// ── 1. Pick a topic ──────────────────────────────────────────────────
		$topics = AIPG_Settings::get_topics();
		if ( empty( $topics ) ) {
			return self::fail( 'No topics configured. Please add topics in the plugin settings.' );
		}

		$topic = $topics[ array_rand( $topics ) ];

		// ── 2. Build the prompt ───────────────────────────────────────────────
		$prompt = self::build_prompt( $topic );

		// ── 3. Call the active AI provider ────────────────────────────────────
		$provider = AIPG_Provider_Factory::make();
		$response = $provider->generate( $prompt );

		if ( is_wp_error( $response ) ) {
			return self::fail( 'Gemini API error: ' . $response->get_error_message(), $topic );
		}

		// ── 4. Parse the response ─────────────────────────────────────────────
		$parsed = self::parse_response( $response );
		if ( ! $parsed ) {
			return self::fail( 'Could not parse Gemini response into post fields.', $topic );
		}

		// ── 5. Insert the post ────────────────────────────────────────────────
		$post_id = self::insert_post( $parsed, $topic );
		if ( is_wp_error( $post_id ) ) {
			return self::fail( 'Failed to insert post: ' . $post_id->get_error_message(), $topic );
		}

		return [
			'success' => true,
			'message' => sprintf( 'Post "%s" created (ID: %d, pending review).', $parsed['title'], $post_id ),
			'post_id' => $post_id,
			'topic'   => $topic,
			'title'   => $parsed['title'],
			'time'    => current_time( 'mysql' ),
		];
	}

	// ── Prompt builder ────────────────────────────────────────────────────────

	private static function build_prompt( string $topic ): string {
		$requirements = AIPG_Settings::get_requirements();
		$language     = AIPG_Settings::get_language();

		// Build requirements list
		$req_lines     = array_filter( array_map( 'trim', explode( "\n", $requirements ) ) );
		$req_formatted = '';
		foreach ( $req_lines as $idx => $line ) {
			$req_formatted .= ( $idx + 1 ) . ". $line\n";
		}

		return <<<PROMPT
You are an expert content writer. Write a high-quality blog article for WordPress.

**Topic:** {$topic}

**Language:** Write the entire article in {$language}.

**Requirements:**
{$req_formatted}

**IMPORTANT – Output format:**
Return your response as a structured block using EXACTLY the markers below.
Do not add anything before <<<TITLE>>> or after <<<END>>>.

<<<TITLE>>>
[Write a compelling, SEO-friendly post title here — plain text, no markdown]
<<<EXCERPT>>>
[Write a 1-2 sentence meta description / excerpt here — plain text]
<<<TAGS>>>
[Comma-separated list of 3-6 relevant tags — plain text]
<<<CONTENT>>>
[Full article body here — you MAY use basic HTML tags: <h2>, <h3>, <p>, <ul>, <ol>, <li>, <strong>, <em>, <blockquote>. Do NOT use <html>, <body>, <head> or any structural tags.]
<<<END>>>
PROMPT;
	}

	// ── Response parser ───────────────────────────────────────────────────────

	/**
	 * Parse the structured markers from the Gemini response.
	 *
	 * @param  string $text
	 * @return array|false
	 */
	private static function parse_response( string $text ) {
		// Clean up potential markdown code fences
		$text = preg_replace( '/^```[a-z]*\n?/m', '', $text );
		$text = preg_replace( '/```$/m', '', $text );

		$sections = [
			'title'   => self::extract_section( $text, 'TITLE', 'EXCERPT' ),
			'excerpt' => self::extract_section( $text, 'EXCERPT', 'TAGS' ),
			'tags'    => self::extract_section( $text, 'TAGS', 'CONTENT' ),
			'content' => self::extract_section( $text, 'CONTENT', 'END' ),
		];

		// Validate required fields
		if ( empty( $sections['title'] ) || empty( $sections['content'] ) ) {
			// Fallback: if markers are missing, treat the whole response as content
			// and auto-generate a title
			if ( strlen( $text ) > 100 ) {
				$lines = array_filter( explode( "\n", trim( $text ) ) );
				$first = reset( $lines );
				return [
					'title'   => wp_strip_all_tags( $first ),
					'excerpt' => '',
					'tags'    => [],
					'content' => $text,
				];
			}
			return false;
		}

		// Parse tags string into array
		$tags_raw = $sections['tags'] ?? '';
		$tags     = array_filter( array_map( 'trim', explode( ',', $tags_raw ) ) );

		return [
			'title'   => sanitize_text_field( $sections['title'] ),
			'excerpt' => sanitize_text_field( $sections['excerpt'] ),
			'tags'    => array_values( $tags ),
			'content' => wp_kses_post( $sections['content'] ),
		];
	}

	/**
	 * Extract text between <<<START_MARKER>>> and <<<END_MARKER>>>
	 */
	private static function extract_section( string $text, string $start, string $end ): string {
		$pattern = '/<<<' . $start . '>>>\s*(.*?)\s*<<<' . $end . '>>>/s';
		preg_match( $pattern, $text, $matches );
		return isset( $matches[1] ) ? trim( $matches[1] ) : '';
	}

	// ── Post insertion ────────────────────────────────────────────────────────

	/**
	 * Insert the generated content as a pending WordPress post.
	 *
	 * @param  array  $parsed   Parsed content fields
	 * @param  string $topic    Original topic string (stored as meta)
	 * @return int|\WP_Error    Post ID or error
	 */
	private static function insert_post( array $parsed, string $topic ) {
		$author_id = AIPG_Settings::get_author_id();

		// Validate author
		if ( ! get_userdata( $author_id ) ) {
			$author_id = 1; // Fall back to admin
		}

		$post_data = [
			'post_title'   => $parsed['title'],
			'post_content' => $parsed['content'],
			'post_excerpt' => $parsed['excerpt'],
			'post_status'  => 'pending',   // Awaiting editorial review
			'post_author'  => $author_id,
			'post_type'    => 'post',
			'post_date'    => current_time( 'mysql' ),
			'meta_input'   => [
				'_aipg_generated'    => '1',
				'_aipg_topic'        => $topic,
				'_aipg_provider'     => AIPG_Settings::get_active_provider(),
				'_aipg_model'        => AIPG_Settings::get_model_for( AIPG_Settings::get_active_provider() ),
				'_aipg_generated_at' => current_time( 'mysql' ),
			],
		];

		// Assign category
		$category = AIPG_Settings::get_post_category();
		if ( $category > 0 ) {
			$post_data['post_category'] = [ $category ];
		}

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// ── Tags ──────────────────────────────────────────────────────────────
		// Merge configured tags with AI-generated tags
		$configured_tags = AIPG_Settings::get_post_tags();
		$all_tags        = array_unique( array_merge( $configured_tags, $parsed['tags'] ) );
		if ( ! empty( $all_tags ) ) {
			wp_set_post_tags( $post_id, $all_tags, false );
		}

		return $post_id;
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	private static function fail( string $message, string $topic = '' ): array {
		return [
			'success' => false,
			'message' => $message,
			'topic'   => $topic,
			'time'    => current_time( 'mysql' ),
		];
	}
}
