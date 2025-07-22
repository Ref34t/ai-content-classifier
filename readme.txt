=== AI Content Classifier ===
Contributors: mokhaled
Tags: ai, content generator, openai, gpt, seo, content creation, automation
Requires at least: 5.0
Tested up to: 6.8
Stable tag: 1.1.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate SEO-optimized WordPress content using OpenAI's GPT API. Create blog posts, pages, and more with AI-powered content generation.

== Description ==

AI Content Classifier is a powerful WordPress plugin that leverages OpenAI's GPT models to help you create high-quality, SEO-optimized content directly from your WordPress dashboard.

= Key Features =

* **AI-Powered Content Generation** - Generate blog posts, pages, product descriptions, and more
* **Multiple AI Models** - Support for GPT-3.5 Turbo, GPT-4, and other OpenAI models
* **SEO Optimization** - Automatically generate meta descriptions, keywords, and excerpts
* **Custom Templates** - Create and save reusable prompt templates
* **Rate Limiting** - Built-in protection against API abuse
* **Security First** - Input sanitization, content filtering, and XSS protection
* **Cost Tracking** - Monitor your API usage and costs
* **REST API** - Programmatic access for developers
* **Bulk Generation** - Generate multiple pieces of content at once

= Use Cases =

* Blog post creation
* Product descriptions
* Email newsletters
* Social media content
* Landing page copy
* FAQ sections

= Privacy and External Services =

This plugin requires an OpenAI API key and sends data to OpenAI's servers for content generation. Please review:

* [OpenAI's Privacy Policy](https://openai.com/policies/privacy-policy)
* [OpenAI's Terms of Service](https://openai.com/policies/terms-of-use)

The plugin only sends data to OpenAI when you explicitly request content generation. No data is sent automatically.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/ai-content-generator` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the Settings->AI Content Classifier screen to configure the plugin
4. Add your OpenAI API key (Get one at https://platform.openai.com)

== Frequently Asked Questions ==

= Do I need an OpenAI API key? =

Yes, you need an active OpenAI API key with billing enabled. You can get one at https://platform.openai.com

= How much does it cost to generate content? =

Costs depend on the model used:
* GPT-3.5 Turbo: ~$0.002 per 1000 tokens
* GPT-4: ~$0.06 per 1000 tokens
A typical 1000-word blog post costs $0.003-$0.08 to generate.

= Is my content stored anywhere? =

Generated content is only stored in your WordPress database when you save it as a post or page. The plugin does not store content externally.

= Can I customize the AI prompts? =

Yes! The plugin includes a template system where you can create and save custom prompts with variables.

= Is there a limit to how much content I can generate? =

The plugin includes rate limiting (10 requests per hour per user by default) to prevent abuse. This can be adjusted by administrators.

= Does this plugin work with WordPress Multisite? =

Yes, the plugin is compatible with WordPress Multisite installations.

== Screenshots ==

1. Content generation interface
2. Template management screen
3. Plugin settings page
4. Generated content preview
5. SEO optimization options

== Changelog ==

= 1.1.0 =
* Security hardening - Fixed all WordPress Plugin Check issues
* Enhanced output escaping for improved security
* Improved input validation across all forms and AJAX handlers
* Added proper sanitization with wp_unslash() implementation  
* Fixed I18n issues with translator comments and ordered placeholders
* Replaced date() with gmdate() for timezone-safe operations
* Replaced deprecated functions with WordPress standard alternatives
* Removed debug code for production readiness
* Enhanced nonce verification for all user interactions
* Improved compatibility with WordPress.org submission standards

= 1.0.0 =
* Initial release
* Core content generation functionality
* Template system
* SEO optimization features
* Security hardening
* REST API support
* Rate limiting
* Cost tracking

== Upgrade Notice ==

= 1.0.0 =
First release of AI Content Classifier plugin.

== Privacy Policy ==

This plugin:
* Only sends data to OpenAI when you request content generation
* Does not track users
* Does not store personal data beyond what WordPress normally stores
* Stores your API key encrypted in the database
* Logs API usage for cost tracking (can be disabled)

For more information, see our [privacy policy](https://mokhaled.dev/privacy).

== Support ==

For support, feature requests, and bug reports, please visit our [support forum](https://wordpress.org/support/plugin/ai-content-generator/) or [GitHub repository](https://github.com/mokhaled/ai-content-generator).

== Development ==

This plugin is open source and available on [GitHub](https://github.com/mokhaled/ai-content-generator). Contributions are welcome!

= Minimum Requirements =

* WordPress 5.0 or greater
* PHP version 7.4 or greater
* MySQL version 5.6 or greater
* cURL extension enabled
* OpenAI API key

== Disclaimer ==

This plugin uses the OpenAI API, which is a third-party service. We are not responsible for the content generated or any costs incurred. Please use this plugin responsibly.

The content generated by AI may not always be accurate or complete. Always review and edit the content before publishing.

We are not affiliated with OpenAI.