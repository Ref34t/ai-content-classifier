# AI Content Classifier

**Generate SEO-optimized WordPress content using OpenAI's GPT API. Create blog posts, pages, and more with AI-powered content generation.**

[![WordPress Plugin Version](https://img.shields.io/badge/version-1.1.1-blue.svg)](https://github.com/ref34t/ai-content-classifier)
[![WordPress Compatibility](https://img.shields.io/badge/wordpress-5.0%2B-green.svg)](https://wordpress.org/)
[![PHP Version](https://img.shields.io/badge/php-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-GPLv2%2B-orange.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

## Description

AI Content Classifier is a powerful WordPress plugin that leverages OpenAI's GPT models to help you create high-quality, SEO-optimized content directly from your WordPress dashboard.

## ✨ Key Features

- **🤖 AI-Powered Content Generation** - Generate blog posts, pages, product descriptions, and more
- **🧠 Multiple AI Models** - Support for GPT-3.5 Turbo, GPT-4, and other OpenAI models
- **📈 SEO Optimization** - Automatically generate meta descriptions, keywords, and excerpts
- **📝 Custom Templates** - Create and save reusable prompt templates
- **🛡️ Rate Limiting** - Built-in protection against API abuse
- **🔒 Security First** - Input sanitization, content filtering, and XSS protection
- **💰 Cost Tracking** - Monitor your API usage and costs
- **🔌 REST API** - Programmatic access for developers
- **📦 Bulk Generation** - Generate multiple pieces of content at once

## 🎯 Use Cases

- Blog post creation
- Product descriptions
- Email newsletters
- Social media content
- Landing page copy
- FAQ sections

## 🚀 Installation

1. Upload the plugin files to the `/wp-content/plugins/ai-content-generator` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the Settings→AI Content Classifier screen to configure the plugin
4. Add your OpenAI API key (Get one at [OpenAI Platform](https://platform.openai.com))

## 📋 Requirements

- WordPress 5.0 or greater
- PHP version 7.4 or greater
- MySQL version 5.6 or greater
- cURL extension enabled
- OpenAI API key with billing enabled

## ⚙️ Configuration

1. Navigate to **AI Content** in your WordPress admin menu
2. Go to **Settings** and enter your OpenAI API key
3. Choose your preferred AI model (GPT-3.5 Turbo recommended for cost-effectiveness)
4. Configure rate limits and other settings as needed

### OpenAI API Setup
1. Sign up at [OpenAI Platform](https://platform.openai.com)
2. Create an API key in your dashboard
3. Add billing information to your account
4. Configure the API key in the plugin settings

## 💡 Usage

### Basic Content Generation
1. Go to **AI Content** → **Generate**
2. Enter your content prompt
3. Select content type (blog post, page, etc.)
4. Click **Generate Content**
5. Review and edit the generated content
6. Save as draft or publish directly

### Template System
1. Navigate to **AI Content** → **Templates**
2. Create custom prompt templates with variables
3. Use templates for consistent content generation
4. Share templates across your team

### Bulk Operations
1. Access **AI Content** → **Bulk Operations**
2. Upload a CSV file with multiple prompts
3. Generate content in batches
4. Monitor progress and download results

## 🔐 Privacy and External Services

This plugin requires an OpenAI API key and sends data to OpenAI's servers for content generation. Please review:

- [OpenAI's Privacy Policy](https://openai.com/policies/privacy-policy)
- [OpenAI's Terms of Service](https://openai.com/policies/terms-of-use)

**Important**: The plugin only sends data to OpenAI when you explicitly request content generation. No data is sent automatically.

## 💰 Pricing

Content generation costs depend on the OpenAI model used:

- **GPT-3.5 Turbo**: ~$0.002 per 1,000 tokens
- **GPT-4**: ~$0.06 per 1,000 tokens
- **Average blog post (1,000 words)**: $0.003-$0.08

The plugin includes built-in cost tracking to help you monitor expenses.

## 🛡️ Security Features

- Input sanitization and validation
- XSS protection
- CSRF protection with nonces
- Capability checks for all actions
- Rate limiting to prevent abuse
- Encrypted API key storage
- Content filtering

## API Usage

### REST Endpoints
```
POST /wp-json/aicg/v1/generate
```

**Parameters:**
- `prompt` (required) - Content generation prompt
- `content_type` (optional) - Type of content (post, page, product, etc.)
- `seo_enabled` (optional) - Enable SEO optimization

**Example:**
```bash
curl -X POST https://yoursite.com/wp-json/aicg/v1/generate \
  -H "Content-Type: application/json" \
  -d '{
    "prompt": "Write about AI in WordPress",
    "content_type": "post",
    "seo_enabled": true
  }'
```

## ❓ Frequently Asked Questions

**Q: Do I need an OpenAI API key?**  
A: Yes, you need an active OpenAI API key with billing enabled. Get one at [OpenAI Platform](https://platform.openai.com).

**Q: How much does it cost to generate content?**  
A: Costs vary by model. GPT-3.5 Turbo costs ~$0.002 per 1,000 tokens, while GPT-4 costs ~$0.06 per 1,000 tokens.

**Q: Is my content stored anywhere externally?**  
A: No, generated content is only stored in your WordPress database when you save it.

**Q: Can I customize the AI prompts?**  
A: Yes! Use the template system to create custom prompts with variables.

**Q: Is there a generation limit?**  
A: The plugin includes rate limiting (10 requests per hour per user by default) to prevent abuse.

**Q: Does this plugin work with WordPress Multisite?**  
A: Yes, the plugin is compatible with WordPress Multisite installations.

## File Structure

```
ai-content-generator/
├── ai-content-generator.php    # Main plugin file
├── includes/
│   ├── class-ai-content-generator.php
│   ├── class-openai-client.php
│   └── class-security.php
├── admin/
│   ├── class-admin-menu.php
│   └── class-settings.php
├── templates/
│   ├── admin-generator.php
│   ├── admin-templates.php
│   └── admin-settings.php
├── assets/
│   ├── css/admin.css
│   └── js/admin.js
└── README.md
```

## Database Tables

### aicg_templates
Stores custom prompt templates:
- `id` - Template ID
- `name` - Template name
- `prompt` - Prompt content
- `content_type` - Content type (post, page, etc.)
- `seo_enabled` - SEO optimization flag
- `created_at` - Creation timestamp

### aicg_usage_log
Tracks API usage:
- `user_id` - WordPress user ID
- `tokens_used` - Number of tokens consumed
- `cost` - Estimated cost
- `model` - AI model used
- `created_at` - Request timestamp

## Hooks and Filters

### Actions
- `aicg_before_generate` - Fires before content generation
- `aicg_after_generate` - Fires after content generation
- `aicg_cleanup_temp_data` - Daily cleanup scheduled event

### Filters
- `aicg_sanitize_prompt` - Filter user prompts
- `aicg_filter_content` - Filter generated content
- `aicg_api_response` - Modify API responses

## Troubleshooting

### Common Issues

**API Key Not Working**
- Verify key starts with 'sk-'
- Check billing is enabled in OpenAI account
- Ensure key has correct permissions

**Rate Limit Exceeded**
- Default limit is 10 requests/hour per user
- Increase limit in code or wait for reset

**Content Not Generating**
- Check WordPress error logs
- Verify cURL is enabled
- Test API key in settings

### Debug Mode
Enable WordPress debug mode to see detailed error messages:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Development

### Local Development
1. Clone the repository
2. Install in WordPress development environment
3. Configure OpenAI API key
4. Test all features

### Contributing
1. Fork the repository
2. Create feature branch
3. Make changes with tests
4. Submit pull request

## License

GPL v2 or later

## Support

For support and questions:
- Check the documentation
- Review WordPress error logs
- Test with minimal plugin setup

## 🐛 Support

For support, feature requests, and bug reports:

- [WordPress.org Support Forum](https://wordpress.org/support/plugin/ai-content-generator/)
- [GitHub Issues](https://github.com/ref34t/ai-content-classifier/issues)
- [Documentation](https://github.com/ref34t/ai-content-classifier/wiki)

## 🤝 Contributing

Contributions are welcome! Please submit pull requests to our [GitHub repository](https://github.com/ref34t/ai-content-classifier).

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## 📝 Changelog

### 1.0.0 (2024-07-18)
- Initial release
- Core content generation functionality
- Template system implementation
- SEO optimization features
- Security hardening
- REST API support
- Rate limiting system
- Cost tracking dashboard

## 📄 License

This plugin is licensed under the [GNU General Public License v2.0 or later](https://www.gnu.org/licenses/gpl-2.0.html).

## 📋 Changelog

### Version 1.1.0 - Security & Standards Update
- **Security Hardening**: Fixed all WordPress Plugin Check issues for WordPress.org compliance
- **Enhanced Output Escaping**: All user-facing content properly escaped for security
- **Improved Input Validation**: Added isset() checks and proper sanitization across all forms and AJAX handlers
- **I18n Improvements**: Added translator comments and ordered placeholders for better internationalization
- **Timezone Safety**: Replaced date() with gmdate() for consistent UTC time handling
- **Code Standards**: Replaced deprecated functions with WordPress standard alternatives
- **Production Ready**: Removed debug code and enhanced nonce verification
- **WordPress.org Compliance**: Plugin now meets all submission standards

### Version 1.0.0 - Initial Release
- Core content generation functionality
- Template system for reusable prompts
- SEO optimization features
- REST API support
- Rate limiting and cost tracking
- Security hardening

## ⚠️ Disclaimer

This plugin uses the OpenAI API, which is a third-party service. We are not responsible for the content generated or any costs incurred. Please use this plugin responsibly.

The content generated by AI may not always be accurate or complete. Always review and edit the content before publishing.

We are not affiliated with OpenAI.

## 🔗 Links

- [Plugin Homepage](https://github.com/ref34t/ai-content-classifier)
- [WordPress.org Plugin Page](https://wordpress.org/plugins/ai-content-generator/)
- [Author Website](https://mokhaled.dev)
- [OpenAI Platform](https://platform.openai.com)

---

**Made with ❤️ for the WordPress community**

