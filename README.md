# HTTP API Debug

This plugin hooks into http_api_debug action to give you a record of the http requests your server is making.
It only logs requests that use the WP_Http object.

## TODO

Options Page

- Purge Policy (daily, weekly, monthly)
- Purge now button - and add to bulk options
- Keep at most X log entries

## Filters and Actions

You can block logging of requests by checking various conditions, like url, response codes, or headers.

In this example, I'm not logging requests for api.wordpress.org, which is the url WordPress talks to when
checking for updates.

```php
function dont_log_wpapi($record_log, $response, $context, $transport_class, $request_args, $url)
{
    if ( parse_url($url, PHP_URL_HOST) == 'api.wordpress.org' )
        return false;

    return $record_log;
}
add_filter('http_api_debug_record_log', 'dont_log_wpapi', 10, 6);
```
