# AI Post Generator — WordPress Plugin

Automatically drafts WordPress posts on a schedule using AI: **Groq, OpenRouter, OpenAI, Gemini, Mistral, Claude**.
All posts are created with **Pending** status, so an editor reviews and approves them before they go live.

---

## Features

- 🤖 **AI-powered** — choose a provider and a model
- 📅 **Flexible scheduling** — hourly, twice daily, daily, or weekly via WP-Cron
- 💡 **Topic list** — one topic per line; a random one is picked each run
- 📋 **Custom requirements** — enforce tone, format, word count, and mandatory sections (e.g., always include sources)
- ✍️ **Dedicated author** — assign a specific WordPress user as author for all AI-generated posts
- 🏷 **Auto-tags & categories** — AI suggests tags; you can set fixed tags and a category too
- 🌐 **Multi-language** — write posts in any language
- ✅ **Pending review** — posts require editorial approval before publishing
- ⚡ **Generate Now** button — test your setup without waiting for the schedule
- 📋 **Generation Log** — audit trail of every run with success/failure details

---

## Installation

1. Upload the `ai-post-generator` folder to `/wp-content/plugins/`
2. Activate the plugin through **Plugins → Installed Plugins**
3. Go to **AI Post Generator** in the WordPress admin menu
4. Enter your **Gemini API Key** (get one free at [Google AI Studio](https://aistudio.google.com/app/apikey))
5. Configure your topics, requirements, schedule, and author
6. Click **Test API Connection** to verify everything works
7. Save settings — the cron job starts immediately

---

## Configuration

### Topics
Enter one topic per line. A random topic is chosen each time the cron runs.

Example:
```
The future of renewable energy in urban cities
How to build a morning routine that actually sticks
Beginner's guide to index fund investing
The impact of social media on teenage mental health
```

### Writing Requirements
One instruction per line. These are sent directly to Gemini.

Example:
```
Write in an engaging, conversational tone suitable for a general audience.
Use clear H2 and H3 subheadings to organize the article.
Include practical, actionable tips where applicable.
The article must be between 700 and 900 words.
At the end, include a "Sources & Further Reading" section with 3-5 credible sources and their URLs.
Avoid clickbait language; be honest and informative.
```

### Author
Select an existing WordPress user. All AI-generated posts will be credited to this user. Make sure the user has at least **Author** role so they can have posts attributed to them.

---

## WP-Cron Note

WordPress cron runs when someone visits your site. On low-traffic sites, consider using a **real cron job** to trigger WP-Cron:

```bash
# Add to your server crontab (runs every 5 minutes)
*/5 * * * * curl -s https://yourdomain.com/wp-cron.php?doing_wp_cron > /dev/null 2>&1
```

Or with WP-CLI:
```bash
*/5 * * * * wp cron event run --due-now --path=/var/www/html > /dev/null 2>&1
```

---

## Reviewing Generated Posts

1. Go to **Posts → All Posts** and filter by **Pending**
2. All AI-generated posts have the meta key `_aipg_generated = 1`
3. Edit, refine, and **Publish** when you're happy with the content
4. A banner in the admin will notify you when posts are awaiting review

---

## Requirements

- WordPress 6.0+
- PHP 8.0+
- Google Gemini API key (free tier available)
- Outbound HTTP requests must be allowed by your server

---

## File Structure

```
ai-post-generator/
├── ai-post-generator.php                    # Plugin bootstrap & constants
├── includes/
│   ├── providers/
│   │   ├── class-provider-anthropic.php     # Anthropic Messages API (Claude models)
│   │   ├── class-provider-base.php          # Abstract base class that every AI provider must extend
│   │   ├── class-provider-factory.php       # Creates the correct provider instance
│   │   ├── class-provider-gemini.php        # Google Gemini AI
│   │   └── class-provider-openai-compat.php # OpenAI ChatGPT AI
│   ├── class-settings.php                   # All option keys & getters
│   ├── class-gemini.php                     # Gemini REST API client
│   ├── class-generator.php                  # Prompt builder & post creator
│   ├── class-cron.php                       # WP-Cron scheduling
│   └── class-admin.php                      # Admin menu, forms, AJAX
├── admin/
│   └── views/
│       ├── settings.php                     # Settings page HTML
│       └── log.php                          # Generation log HTML
└── assets/
    ├── admin.css                            # Admin styles
    └── admin.js                             # Admin interactions
```

---

## License

GPL v2 or later.
