# Online.net API proxy

[Online.net API Documentation](https://console.online.net/en/api/)
---

 * Proxies the Online API requests
 * Filter access to certain IP and Server IDs
 * Logs the requests

This works by doing a **GET** request to the proxy php page.

Required parameters:
---
* _api_ - The value set to $config['proxy_api_key']
* _method_ - The online API method (GET, POST, etc.)
* _endpointrequest_ - The  request (e.g. /server/ip/edit). You can find those in the Online.net API docs.

Any other parameters should be passed with the ?key[] and ?value[] GET params.

*key* indicating the name of the parameter, and *value* indicating the value to corresponding key.


**Example request**: proxy.php?api=changeme&endpointrequest=/server/reboot/1234&method=POST&key[]=reason&value[]=reason+goes+here&key[]=email&value[]=mailgoeshere

