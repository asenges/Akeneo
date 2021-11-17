<?php
/**
 * Shopify REST API HTTP Client
 */

namespace Webkul\ShopifyBundle\Classes;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

/**
 * Api cleint
 *
 */
class ApiClient
{
    protected $versionClass;
    /**
     * cURL handle.
     *
     * @var resource
     */
    protected $ch;

    /**
     * Store API URL.
     *
     * @var string
     */
    protected $url;

    /**
     * Consumer key.
     *
     * @var string
     */
    protected $consumerKey;

    /**
     * Consumer secret.
     *
     * @var string
     */
    protected $consumerSecret;

    /**
     * Client options.
     *
     * @var Options
     */
    protected $options;

    /**
     * Request.
     *
     * @var Request
     */
    private $request;

    /**
     * Response.
     *
     * @var Response
     */
    private $response;

    /**
     * Response headers.
     *
     * @var string
     */
    private $responseHeaders;

    /**
     * Initialize HTTP client.
     *
     * @param string $url            Store URL.
     * @param string $consumerKey    Api key.
     * @param string $consumerSecret Api Password.
     * @param array  $options        Client options.
     */
    public function __construct($url, $consumerKey, $consumerSecret, $apiVersion, $options = [])
    {
        if (!\function_exists('curl_version')) {
            throw new HttpClientException('cURL is NOT installed on this server', -1, new Request(), new Response());
        }

        $this->versionClass   = $apiVersion;
        $this->options        = $options;
        $this->url            = $this->buildApiUrl($url);
        $this->consumerKey    = $consumerKey;
        $this->consumerSecret = $consumerSecret;
    }

    /**
     * Build API URL.
     *
     * @param string $url Store URL.
     *
     * @return string
     */
    protected function buildApiUrl($url)
    {
        $url = str_replace(['http://'], ['https://'], $url);
        return \rtrim($url, '/') . '/admin/api/' . $this->allVersions[$this->versionClass] . '/';
    }

    /**
     * Build URL.
     *
     * @param string $url        URL.
     * @param array  $parameters Query string parameters.
     *
     * @return string
     */
    protected function buildUrlQuery($url, $parameters = [])
    {
        if (!empty($parameters)) {
            $url .= '?' . \http_build_query($parameters);
        }

        return $url;
    }

    /**
     * Authenticate.
     *
     * @param string $url        Request URL.
     * @param string $method     Request method.
     * @param array  $parameters Request parameters.
     *
     * @return array
     */
    protected function authenticate($url, $method, $parameters)
    {
        \curl_setopt($this->ch, CURLOPT_URL, $url);
        \curl_setopt($this->ch, CURLOPT_HTTPHEADER, $this->getRequestHeaders());
    }

    /**
     * Setup method.
     *
     * @param string $method Request method.
     */
    protected function setupMethod($method)
    {
        if ('POST' == $method) {
            \curl_setopt($this->ch, CURLOPT_POST, true);
        } elseif (\in_array($method, ['PUT', 'DELETE', 'OPTIONS'])) {
            \curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, $method);
        }
    }

    /**
     * Get request headers.
     *
     * @param  bool $sendData If request send data or not.
     *
     * @return array
     */
    protected function getRequestHeaders()
    {
        $headers = [
            'Accept: application/json',
            'Content-type: application/json',
            'Cache-Control: no-cache',
            'Cache-Control: max-age=0',
            'Authorization: Basic ' . base64_encode($this->consumerKey . ':' . $this->consumerSecret),
        ];

        return $headers;
    }

    /**
     * Create request.
     *
     * @param string $endpoint   Request endpoint.
     * @param string $method     Request method.
     * @param array  $data       Request data.
     * @param array  $parameters Request parameters.
     *
     * @return Request
     */
    protected function createRequest($endpoint, $parameters = [], $data = [], $logger = null)
    {
        if (!array_key_exists($endpoint, $this->endpoints)) {
            return;
        }

        $method = $this->endpoints[$endpoint]['method'];
        $endpoint = $this->endpoints[$endpoint]['url'];
        foreach ($parameters as $key => $val) {
            $endpoint = str_replace('{_' . $key . '}', $val, $endpoint);
        }

        $body    = '';
        $endpoint = str_replace('{_limit}', 50, $endpoint);
        $url     = $this->url . $endpoint;
        
        // $url     = $this->buildUrlQuery($url, $parameters);
        $hasData = !empty($data);

        // Setup authentication.
        $this->authenticate($url, $method, $parameters);

        // Setup method.
        $this->setupMethod($method);
        // Include post fields.
        if ($hasData) {
            $body = json_encode($data);
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $body);
        }

        if (!empty($logger) && $logger instanceof \Webkul\ShopifyBundle\Logger\ApiLogger) {
            $logger->info("Request URL: $url, Request Method: $method, Request Data: $body");
        }
    }

    /**
     * Get response headers.
     *
     * @return array
     */
    protected function getResponseHeaders()
    {
        $headers = [];
        $lines   = \explode("\n", $this->responseHeaders);
        $lines   = \array_filter($lines, 'trim');

        foreach ($lines as $index => $line) {
            // Remove HTTP/xxx params.
            if (strpos($line, ': ') === false) {
                continue;
            }

            list($key, $value) = \explode(': ', $line);

            $headers[$key] = isset($headers[$key]) ? $headers[$key] . ', ' . trim($value) : trim($value);
        }

        return $headers;
    }

    /**
     * Create response.
     *
     * @return Response
     */
    protected function createResponse()
    {
        // Get response data.

        $body    = \curl_exec($this->ch);
        $code    = \curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        $code    = \curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($this->ch, CURLINFO_HEADER_SIZE);
        $headers = substr($body, 0, $header_size);
        $body = explode("\r\n", $body);
        try {
            $matches  = array_values(preg_grep('/Link/i', $body));
            if (!empty($matches) && strpos($matches[0], '>; rel="next')) {
                $link = explode(":", $matches[0], 2);
                $start  = strripos($link[1], 'page_info=');
                $end    = strripos($link[1], '>; rel="next', $start + 10);
                $length = $end - $start;
                $result = substr($link[1], $start + 10, $length - 10);
            }

            $count = count($body) - 1;
            $body = json_decode($body[$count], true);
        } catch (\Exception $e) {
            $body = [];
        }
        if (!empty($body) && gettype($body) != 'integer' && gettype($body) != 'boolean') {
            $response = array_merge(['code' => $code], $body);
            if (!empty($result)) {
                $response['link'] = $result;
            }
        } else {
            $response = [ 'code' => $code ];
        }

        // Register response.
        return $response;
    }

    /**
     * Set default cURL settings.
     */
    protected function setDefaultCurlSettings()
    {
        $verifySsl       = !empty($this->options['verifySsl']) ? $this->options['verifySsl'] : false;
        $timeout         = !empty($this->options['timeout']) ? $this->options['timeout'] : 120 ; // default by Webkul: 60
        $followRedirects = !empty($this->options['followRedirects']) ? $this->options['followRedirects'] : true;

        \curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, $verifySsl);
        if (!$verifySsl) {
            \curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, $verifySsl);
        }
        if ($followRedirects) {
            \curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, $followRedirects);
        }
        \curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        \curl_setopt($this->ch, CURLOPT_TIMEOUT, $timeout);
        \curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($this->ch, CURLOPT_HEADER, 1);
    }


    /**
     * Make requests.
     *
     * @param string $endpoint   Request endpoint.
     * @param array  $parameters Request parameters.
     * @param array  $data       Request data.
     *
     * @return array
     */
    public function request($endpoint, $parameters = [], $payload = [], $logger = null)
    {
        // Initialize cURL.
        $this->ch = \curl_init();
        // Set request args.
        $request = $this->createRequest($endpoint, $parameters, $payload, $logger);
        // Default cURL settings.
        $this->setDefaultCurlSettings();
       
        // Get response.
        $response = $this->createResponse();
        // var_dump($payload);
        /** Rate Limit Handled */
        if (isset($response['code']) && $response['code'] == Response::HTTP_TOO_MANY_REQUESTS) {
            sleep(4);
            $response = $this->request($endpoint, $parameters, $payload, $logger);
        } else {
            // Check for cURL errors.
            if (\curl_errno($this->ch)) {
                $response['error'] = \curl_error($this->ch);
                $response['code'] = 0;
            }

            \curl_close($this->ch);
        }

        return $response;
    }

    /**
     * Get request data.
     *
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Get response data.
     *
     * @return Response
     */
    public function getResponse()
    {
        return $this->response;
    }

    protected $allVersions = [
        '2021_04' => '2021-04'

    ];

    protected $endpoints = [
        'addCategory' => [
            'url'    => 'custom_collections.json',
            'method' => 'POST',
        ],
        'updateCategory' => [
            'url'    => 'custom_collections/{_id}.json',
            'method' => 'PUT',
        ],
        'getCategory' => [
            'url'    => 'custom_collections/{_id}.json',
            'method' => 'GET',
        ],
        'getCategories' => [
            'url'    => 'custom_collections.json',
            'method' => 'GET',
        ],
        'getSmartCategories' =>[
            'url'    => 'smart_collections.json',
            'method' => 'GET',
        ],
        'getCategoriesByLimitPage' => [
            'url'    => 'custom_collections.json?limit={_limit}&page_info={_page_info}',
            'method' => 'GET',
        ],
        'getSmartCategoriesByLimitPage' =>[
            'url'    => 'smart_collections.json?limit={_limit}&page_info={_page_info}',
            'method' => 'GET',
        ],
        'getCategoriesByProductId' => [
            'url'    => 'custom_collections.json?product_id={_product_id}',
            'method' => 'GET',
        ],
        'getSmartCategoriesByProductId' =>[
            'url'    => 'smart_collections.json?product_id={_product_id}',
            'method' => 'GET',
        ],
        'getProducts' => [
            'url'    => 'products.json',
            'method' => 'GET',
        ],
        'getProductsByCollection' => [
            'url'    => 'products.json?collection_id={_collection_id}',
            'method' => 'GET',
        ],
        'getProductsByFields' => [
            'url'    => 'products.json?fields={_fields}',
            'method' => 'GET',
        ],
        'getProductsByFieldsUrl' => [
            'url'    => 'products.json?page_info={_page_info}&fields={_fields}',
            'method' => 'GET',
        ],
        'getProductsByPage' => [
            'url'    => 'products.json?limit=10',
            'method' => 'GET',
        ],
        'getProductsByUrl' => [
            'url'    => 'products.json?limit=10&page_info={_page_info}',
            'method' => 'GET',
        ],
        'getOneProduct' => [
            'url'    => 'products.json?limit=1',
            'method' => 'GET',
        ],
        'addProduct' => [
            'url'    => 'products.json',
            'method' => 'POST',
        ],
        'getProduct' => [
            'url'    => 'products/{_id}.json',
            'method' => 'GET',
        ],
        'updateProduct' => [
            'url'    => 'products/{_id}.json',
            'method' => 'PUT',
        ],
        'addImages' => [
            'url'    => 'products/{_product}/images.json',
            'method' => 'POST',
        ],
        'updateImages' => [
            'url'    => 'products/{_product}/images.json',
            'method' => 'PUT',
        ],
        'getVariations' => [
            'url'    => 'products/{_product}/variants.json',
            'method' => 'GET',
        ],
        'addVariation' => [
            'url'    => 'products/{_product}/variants.json',
            'method' => 'POST',
        ],
        'updateVariation' => [
            'url'    => 'variants/{_id}.json',
            'method' => 'PUT',
        ],
        'getVariation' => [
            'url'    => 'variants/{_id}.json',
            'method' => 'GET',
        ],
        'getImages' => [
            'url'    => 'products/{_product}/images.json',
            'method' => 'GET',
        ],
        'addImage' => [
            'url'    => 'products/{_product}/images.json',
            'method' => 'POST',
        ],
        'updateImage' => [
            'url'    => 'products/{_product}/images/{_id}.json',
            'method' => 'PUT',
        ],
        // 'addToCategory' => [
        //     'url'    => 'custom_collections/{_id}.json',
        //     'method' => 'PUT',
        // ],
        'addToCategory' => [
            'url'    => 'collects.json',
            'method' => 'POST',
        ],
        'getCategoryId' => [
            'url' => 'collects.json?product_id={_id}',
            'method' => 'GET',
        ],
        'deleteCollection' => [
            'url'    => 'collects/{_id}.json',
            'method' => 'DELETE',
        ],
        'getVariantMetafields' => [
            'url'    => 'products/{_product}/variants/{_variant}/metafields.json?limit={_limit}',
            'method' => 'GET',
        ],
        'getVariantMetafieldsByUrl' => [
            'url'    => 'products/{_product}/variants/{_variant}/metafields.json?page_info={_page_info}&limit={_limit}',
            'method' => 'GET',
        ],
        'updateVariantMetafield' => [
            'url'    => 'products/{_product}/variants/{_variant}/metafields/{_id}.json',
            'method' => 'PUT',
        ],
        'deleteVariantMetafield' => [
            'url'    => 'products/{_product}/variants/{_variant}/metafields/{_id}.json',
            'method' => 'DELETE',
        ],
        'getProductMetafields' => [
            'url'    => 'products/{_id}/metafields.json?limit={_limit}',
            'method' => 'GET',
        ],
        'getProductMetafieldsByUrl' => [
            'url'    => 'products/{_id}/metafields.json?page_info={_page_info}&limit={_limit}',
            'method' => 'GET',
        ],
        'addProductMetafields' => [
            'url'    => 'products/{_product}/metafields.json',
            'method' => 'POST'
        ],
        'updateProductMetafield' => [
            'url'    => 'products/{_product}/metafields/{_id}.json',
            'method' => 'PUT',
        ],
        'deleteProductMetafield' => [
            'url'    => 'products/{_product}/metafields/{_id}.json',
            'method' => 'DELETE',
        ],
        'locations' => [
            'url'    => 'locations.json',
            'method' => 'GET',
        ],
        'set_inventory_levels' => [
            'url'    => 'inventory_levels/set.json',
            'method' => 'POST',
        ],
        'get_inventory_list' => [
            'url'    => 'inventory_items/{_id}.json',
            'method' => 'GET',
        ],
        'update_inventory_list' => [
            'url'    => 'inventory_items/{_id}.json',
            'method' => 'PUT',
        ],
        'getCategoriesByLimitPageFields' => [
            'url'    => 'custom_collections.json?fields={_fields}&limit={_limit}&page_info={_page_info}',
            'method' => 'GET',
        ],

    ];
}
