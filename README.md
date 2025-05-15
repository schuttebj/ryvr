# Ryvr AI Platform

A WordPress plugin that provides a powerful digital agency automation platform leveraging OpenAI and DataForSEO APIs to automate SEO, PPC, and content tasks.

## Description

Ryvr AI Platform is an extensible solution designed for digital agencies and small businesses to automate various marketing tasks. It integrates with OpenAI for content generation and DataForSEO for SEO analysis, providing a comprehensive suite of tools to streamline agency operations.

### Core Features

- **Task Engine**: Modular system for executing and monitoring automation workflows
- **API Integration**: Secure integration with OpenAI and DataForSEO APIs
- **Notification System**: Flexible notification system with email, webhooks, and in-platform alerts
- **Content Generation**: AI-powered content generation with customizable templates
- **SEO Tools**: Keyword research, technical SEO audits, and content optimization
- **Advanced Analytics**: Performance tracking and reporting
- **Client Management**: Client onboarding and management capabilities
- **Industry Benchmarks**: Compare performance against industry standards

## Installation

### Requirements

- WordPress 5.9 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

### Manual Installation

1. Download the latest release from the [Releases](https://github.com/schuttebj/ryvr/releases) page
2. Upload the plugin files to the `/wp-content/plugins/ryvr-ai-platform` directory
3. Activate the plugin through the 'Plugins' screen in WordPress
4. Configure the plugin by going to the 'Ryvr AI' menu in your admin dashboard

### Via GitHub

```bash
cd wp-content/plugins/
git clone https://github.com/schuttebj/ryvr.git ryvr-ai-platform
cd ryvr-ai-platform
```

## Configuration

1. Navigate to the Ryvr AI > Settings page in your WordPress admin
2. Enter your API credentials:
   - OpenAI API Key
   - DataForSEO API Login and Password
3. Configure your notification preferences
4. Set up your task processing settings

## Development

### Debug Mode

The plugin includes a comprehensive debugging system. To enable debug mode:

1. Set the `RYVR_DEBUG` constant to `true` in the main plugin file
2. Access the Debug Logs page from the Ryvr AI admin menu
3. Use the following functions in your code to log information:
   - `ryvr_log_debug($message, $level, $component, $context)`: General purpose logging
   - `ryvr_dump($var, $component)`: Dump variables for debugging
   - `ryvr_log_api($service, $endpoint, $request, $response)`: Log API interactions

### Code Structure

- `includes/`: Core plugin functionality
  - `admin/`: Admin interface classes
  - `api/`: API service integrations
  - `core/`: Core functionality and utilities
  - `database/`: Database interaction
  - `notifications/`: Notification system
  - `task-engine/`: Task scheduling and execution
- `assets/`: Frontend assets (CSS, JS, images)
- `templates/`: Template files for various views
- `child-plugin/`: Client-side plugin for deployment

## Contributing

1. Fork the repository
2. Create your feature branch: `git checkout -b feature/my-new-feature`
3. Commit your changes: `git commit -am 'Add some feature'`
4. Push to the branch: `git push origin feature/my-new-feature`
5. Submit a pull request

## License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.

## Acknowledgements

- OpenAI for their powerful AI models
- DataForSEO for comprehensive SEO data access
- All contributors who have helped shape this project 