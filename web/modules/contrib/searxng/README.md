# SearXNG Drupal Module

## Overview

The SearXNG module integrates the [SearXNG](https://docs.searxng.org/) open-source search engine with Drupal. It provides a configurable connector to query SearXNG instances.

Includes optional submodule **searxng_ai_agent** for AI tool integration.

## Installation

Enable the module:
```bash
drush en searxng -y
drush cr
```

Configure at: `/admin/config/search/searxng`

## Configuration

### Required
- **SearXNG Endpoint URL**: Full URL to your SearXNG API endpoint (e.g., `http://localhost:8888/search`)

### Optional
- **API Key**: Authentication token if required by your SearXNG instance
- **Default Categories**: Comma-separated list (e.g., `general,images`)
- **Default Language**: Language code (e.g., `en`, `de`)
- **Safesearch Level**: Off (0), Moderate (1), or Strict (2)
- **Request Timeout**: Seconds to wait for response (default: 30)

## Usage

### Service (SearxngClient)

Basic search:
```php
$client = \Drupal::service('searxng.client');
$results = $client->search('your query');
```

With options:
```php
$results = $client->search('cat photos', [
  'categories' => 'images',
  'safesearch' => 2,
  'language' => 'en',
]);
```

### Search Block

1. Go to: **Structure -> Block layout**
2. Place **"SearXNG search"** block in desired region
3. Use `?q=search+term` URL parameter to auto-search

### AI Agent (searxng_ai_agent submodule)

Enable the submodule for AI tool integration:
```bash
drush en searxng_ai_agent -y
drush cr
```

Provides the **Searxng agent** tool for AI workflows, allowing agents to perform web searches.

## Error Handling

Errors are logged to the `searxng` logger channel:
```bash
drush watchdog:show searxng
```

Common issues:
- **Endpoint not configured**: Set endpoint in admin settings
- **Cannot reach SearXNG**: Verify endpoint URL and SearXNG instance status
- **Invalid JSON response**: Ensure default format is set to `json`
- **Timeout errors**: Increase timeout in admin settings

## Testing

Quick CLI test:
```bash
drush php:eval '$c = \Drupal::service("searxng.client"); print_r($c->search("test"));'
```

## Docker Integration (DDEV)

### Internal Network
```yaml
# .ddev/docker-compose.searxng.yml
services:
  searxng:
  container_name: ddev-${DDEV_SITENAME}-searxng
  image: searxng/searxng:latest
  command: [ "searxng", "serve", "--host", "0.0.0.0", "--port", "8080" ]
  labels:
    com.ddev.site-name: ${DDEV_SITENAME}
    com.ddev.approot: ${DDEV_APPROOT}
  volumes:
    - ./searxng:/etc/searxng
  environment:
    - HTTP_EXPOSE=8888:8080
    - HTTPS_EXPOSE=8889:8080
    - VIRTUAL_HOST=${DDEV_SITENAME}.ddev.site
    - SERVER_NAME=${DDEV_SITENAME}.ddev.site
```

Configure endpoint: `http://searxng:8080/search`
Update the settings.yml by adding json as allowed format (default is html).

### Host Access
Configure endpoint: `http://host.docker.internal:8888/search`

## File Structure

```
searxng/
├── README.md
├── searxng.info.yml
├── searxng.services.yml
├── config/
│   ├── install/
│   │   └── searxng.settings.yml
│   └── schema/
│       └── searxng.schema.yml
├── src/
│   ├── Form/
│   │   ├── SearxngSettingsForm.php
│   │   └── SearxngSearchForm.php
│   ├── Service/
│   │   └── SearxngClient.php
│   └── Plugin/Block/
│       └── SearxngSearchBlock.php
└── modules/
    └── searxng_ai_agent/
        ├── searxng_ai_agent.info.yml
        └── src/Plugin/tool/Tool/
            └── SearxngTool.php
```

## API Reference

See [SearXNG API Documentation](https://docs.searxng.org/dev/search_api.html) for full parameter details.

Common parameters:
- `q`: Search query
- `format`: Response format (json, html, rss)
- `categories`: Categories to search
- `language`: Language code
- `safesearch`: Safesearch level (0, 1, 2)
- `pageno`: Result page number

## License

GPLv2+ (same as Drupal core)
