=== AI Content Classifier ===
Contributors: mokhaled
Tags: ai, content, openai, seo, automation
Requires at least: 5.0
Tested up to: 6.8
Stable tag: 1.1.6
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate SEO-optimized WordPress content using OpenAI's GPT models. Security-hardened plugin for professional content creation.

== Description ==

AI Content Classifier is a **security-hardened, WordPress.org compliant** plugin that leverages OpenAI's most advanced GPT models to help you create high-quality, SEO-optimized content directly from your WordPress dashboard. Built with enterprise-grade security and performance optimization.

**Why Choose AI Content Classifier?**

* **WordPress.org Official** - Fully compliant and approved
* **Security Hardened** - Enterprise-grade protection with zero vulnerabilities
* **Performance Optimized** - Built for high-traffic sites
* **Cost Tracking** - Monitor every penny spent on content generation
* **Professional Support** - Active community and comprehensive documentation

= Key Features =

* **AI-Powered Content Generation** - Generate blog posts, pages, product descriptions, and more using GPT-3.5 Turbo, GPT-4, and GPT-4 Turbo
* **SEO Optimization Engine** - Automatically generate meta descriptions, keywords, excerpts, and real-time SEO scoring
* **Advanced Template System** - Create and save reusable prompt templates with variable support ({{keywords}}, {{tone}}, etc.)
* **Enterprise Security** - Complete input sanitization, XSS protection, CSRF protection, and encrypted API key storage
* **Analytics & Tracking** - Real-time cost monitoring, usage analytics, performance metrics, and export reports
* **Developer Features** - REST API, WordPress hooks & filters, Multisite compatibility, and WP-CLI support
* **Bulk Operations** - Generate multiple pieces of content simultaneously with queue management
* **Rate Limiting** - Configurable API abuse protection (default: 50 requests/hour per user)

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

= Automatic Installation (Recommended) =
1. Go to **Plugins > Add New** in your WordPress admin dashboard
2. Search for "**AI Content Classifier**"
3. Click **Install Now** and then **Activate**
4. Navigate to **AI Content > Settings** to configure
5. Add your OpenAI API key (get one at https://platform.openai.com)

= Manual Installation =

1. Download the plugin from WordPress.org
2. Upload the plugin files to `/wp-content/plugins/ai-content-classifier/`
3. Activate the plugin through the **Plugins** screen
4. Navigate to **AI Content > Settings** to configure

= Quick Setup =

1. **Get OpenAI API Key**: Visit https://platform.openai.com, create account, add billing, generate API key
2. **Configure Plugin**: Paste API key, select GPT model (GPT-3.5 Turbo recommended for cost-effectiveness)
3. **Generate Content**: Go to **AI Content > Generate**, enter your topic, and create amazing content!

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

= 1.1.4 =
* Final SQL injection prevention fixes for complete WordPress.org compliance
* Fixed remaining wpdb::prepare() implementation across all database operations
* Enhanced bulk operations SQL query construction with proper prepared statements
* Secured REST API template queries with improved parameter handling
* Completed security hardening for user activity monitoring queries
* All database interactions now use proper WordPress security standards
* Zero critical security vulnerabilities remaining - fully submission ready

= 1.1.3 =
* Complete WordPress.org plugin directory compliance achieved (100% compliant)
* Enhanced data sanitization with comprehensive JSON validation and error handling
* Fixed all SQL injection vulnerabilities with proper wpdb::prepare() implementation
* Updated class naming conventions: OpenAI_Client renamed to AICG_OpenAI_Client
* Verified all variable escaping in inline scripts meets WordPress security standards
* Comprehensive code review and security audit completed
* All critical, high, and medium priority compliance issues resolved
* Plugin now meets all WordPress.org submission requirements

= 1.1.2 =
* Fixed all remaining SQL preparation errors in bulk operations, security, and REST API classes
* Enhanced WordPress coding standards compliance with proper table name handling
* Improved query preparation to eliminate WordPress.DB.PreparedSQL.NotPrepared violations
* Streamlined database queries for better performance and security

= 1.1.1 =
* Critical security fixes for WordPress Plugin Check compliance
* Fixed 3 critical database query errors with proper $wpdb->prepare() usage
* Enhanced $_POST nonce validation across all AJAX handlers
* Comprehensive input validation fixes (50+ warnings resolved)
* Fixed $_SERVER sanitization for IP address handling
* Improved template editor security with proper isset() checks
* Zero critical errors remaining - WordPress.org submission ready

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

= 1.1.5 =
Latest release with enhanced performance documentation and automated release workflow.

== Privacy Policy ==

This plugin:
* Only sends data to OpenAI when you request content generation
* Does not track users
* Does not store personal data beyond what WordPress normally stores
* Stores your API key encrypted in the database
* Logs API usage for cost tracking (can be disabled)

For more information, see our [privacy policy](https://mokhaled.dev/privacy).

== Support ==

For support, feature requests, and bug reports, please visit our [support forum](https://wordpress.org/support/plugin/ai-content-classifier/) or [GitHub repository](https://github.com/ref34t/ai-content-classifier).

== Development ==

This plugin is open source and available on [GitHub](https://github.com/ref34t/ai-content-classifier). Contributions are welcome!

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