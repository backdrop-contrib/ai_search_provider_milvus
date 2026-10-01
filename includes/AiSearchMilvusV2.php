<?php

/**
 * Extends Milvus with extra calls.
 */
class AiSearchMilvusV2 {

  /**
   * API Token.
   *
   * @var string
   */
  private string $apiKey = '';

  /**
   * The base URL.
   *
   * @var string
   */
  private string $baseUrl = '';

  /**
   * The port.
   *
   * @var int
   */
  private int $port = 443;

  /**
   * Constructor.
   */
  public function __construct() {
  }

  /**
   * Set the API key.
   *
   * @param string $apiKey
   *   The API key.
   */
  public function setApiKey(string $apiKey) {
    $this->apiKey = $apiKey;
  }

  /**
   * Set the base URL.
   *
   * @param string $baseUrl
   *   The base URL.
   */
  public function setBaseUrl(string $baseUrl) {
    $this->baseUrl = $baseUrl;
  }

  /**
   * Set the port.
   *
   * @param int $port
   *   The port.
   */
  public function setPort(int $port) {
    $this->port = $port;
  }

  /**
   * Create collection.
   *
   * @param string $collection_name
   *   The collection.
   * @param string $database_name
   *   The database.
   * @param int $dimension
   *   The dimension.
   * @param string $metric_type
   *   The metric type.
   * @param array $options
   *   Extra options.
   */
  public function createCollection(string $collection_name, string $database_name, int $dimension, string $metric_type, array $options = []) {
    $options['collectionName'] = $collection_name;
    $options['dimension'] = $dimension;
    $options['metricType'] = $metric_type;
    if (!$this->isZilliz()) {
      $options['dbName'] = $database_name;
    }
    // Top-level autoID only (try TRUE and see what happens):
    $options['autoID'] = $options['autoID'] ?? TRUE;
    $options['schema']['autoID'] = TRUE;
    // Metadata fields are added dynamically by the Search API contextual
    // field pipeline. Disabling dynamic fields makes valid indexed metadata
    // fail at insert time unless every possible field is predeclared.
    $options['schema']['enableDynamicField'] = TRUE;

    $response = $this->makeRequest('vectordb/collections/create', [], 'POST', $options);
    $decoded = json_decode($response, TRUE);
    return is_array($decoded) ? $decoded : [];
  }

  /**
   * Drop collection.
   *
   * @param string $collection_name
   *   The collection.
   * @param string $database_name
   *   The database.
   *
   * @return array
   *   The response.
   */
  public function dropCollection(string $collection_name, string $database_name = ''): array {
    $params = [
      'collectionName' => $collection_name,
    ];
    if ($database_name && !$this->isZilliz()) {
      $params['dbName'] = $database_name;
    }
    $response = $this->makeRequest('vectordb/collections/drop', [], 'POST', $params);
    $decoded = json_decode($response, TRUE);
    if (!is_array($decoded)) {
      // Log the raw response and the JSON error so callers/operators can
      // distinguish between an empty result and an API/parse failure.
      $raw_preview = is_string($response) ? substr($response, 0, 2000) : '';
      watchdog('ai_search_milvus', 'vectordb collections drop returned invalid JSON: @err; response preview: @resp', ['@err' => json_last_error_msg(), '@resp' => $raw_preview], WATCHDOG_ERROR);
      return [
        'error' => 'invalid_json',
        'message' => json_last_error_msg(),
        'raw' => is_string($response) ? $response : '',
      ];
    }
    return $decoded;
  }

  /**
   * List collections.
   *
   * @param string $database_name
   *   The database.
   *
   * @return array
   *   The collections.
   */
  public function listCollections(string $database_name = ''): array {
    // Has to be an object, when empty ¯\_(ツ)_/¯.
    $data = $database_name && !$this->isZilliz() ? ['dbName' => $database_name] : new \stdClass();
    $decoded = json_decode($this->makeRequest('vectordb/collections/list', [], 'POST', $data), TRUE);
    return is_array($decoded) ? $decoded : [];
  }

  /**
   * Describe collection.
   *
   * @param string $database_name
   *   The database.
   * @param string $collection_name
   *   The collection name.
   *
   * @return array
   *   The collections.
   */
  public function describeCollection(
    string $database_name = '',
    string $collection_name = '',
  ): array {
    $data = [
      'collectionName' => $collection_name,
    ];
    if ($database_name && !$this->isZilliz()) {
      $data['dbName'] = $database_name;
    }
    $decoded = json_decode($this->makeRequest('vectordb/collections/describe', [], 'POST', $data), TRUE);
    return is_array($decoded) ? $decoded : [];
  }

  /**
   * Insert into the collection.
   *
   * @param string $collection_name
   *   The collection.
   * @param array $data
   *   The data.
   * @param string $database_name
   *   The database.
   *
   * @return array
   *   The response.
   */
  public function insertIntoCollection(string $collection_name, array $data, string $database_name = ''): array {
    $params = [
      'collectionName' => $collection_name,
      'data' => [$data],
    ];
    if ($database_name && !$this->isZilliz()) {
      $params['dbName'] = $database_name;
    }

    $response = $this->makeRequest('vectordb/entities/insert', [], 'POST', $params);
    $decoded = json_decode($response, TRUE);
    return is_array($decoded) ? $decoded : [];
  }

  /**
   * Delete from the collection.
   *
   * @param string $collection_name
   *   The collection.
   * @param array $ids
   *   The ids.
   * @param string $database_name
   *   The database name.
   *
   * @return array
   *   The response.
   */
  public function deleteFromCollection(string $collection_name, array $ids, string $database_name = 'default'): array {
    // Validate and sanitize IDs before building the filter to avoid
    // interpolating unexpected values into the request.
    if (empty($ids)) {
      return ['error' => 'No ids provided'];
    }
    $sanitized = [];
    $invalid = [];
    foreach ($ids as $id) {
      // Accept integers or numeric strings that represent integers.
      if (is_int($id) || (is_string($id) && ctype_digit($id))) {
        $sanitized[] = (int) $id;
      }
      // Reject non-integer numeric values to avoid unintended casting.
      else {
        $invalid[] = $id;
      }
    }
    if (!empty($invalid)) {
      return ['error' => 'Invalid id(s) provided', 'invalid_ids' => $invalid];
    }

    $params = [
      'collectionName' => $collection_name,
      'filter' => 'id in [' . implode(',', $sanitized) . ']',
    ];
    if ($database_name && !$this->isZilliz()) {
      $params['dbName'] = $database_name;
    }
    $decoded = json_decode($this->makeRequest('vectordb/entities/delete', [], 'POST', $params), TRUE);
    return is_array($decoded) ? $decoded : [];
  }

  /**
   * Query collection.
   *
   * @param string $collection_name
   *   The collection.
   * @param array $output_fields
   *   The output fields.
   * @param string $filters
   *   The filters.
   * @param int $limit
   *   The limit.
   * @param int $offset
   *   The offset.
   * @param string $database_name
   *   The database.
   *
   * @return array
   *   The response.
   */
  public function query(string $collection_name, array $output_fields, string $filters = 'id not in [0]', int $limit = 10, int $offset = 0, string $database_name = ''): array {
    $params = [
      'collectionName' => $collection_name,
      'filter' => $filters,
      'outputFields' => $output_fields,
      'limit' => $limit,
      'offset' => $offset,
    ];
    // Only when its Zilliz.
    if ($database_name && !$this->isZilliz()) {
      $params['dbName'] = $database_name;
    }

    $response = $this->makeRequest('vectordb/entities/query', [], 'POST', $params);
    $decoded = json_decode($response, TRUE);
    return is_array($decoded) ? $decoded : [];
  }

  /**
   * Search.
   *
   * @param string $collection_name
   *   The collection.
   * @param array $vector_input
   *   The vector input.
   * @param array $output_fields
   *   The output fields.
   * @param string $filters
   *   The filters.
   * @param int $limit
   *   The limit.
   * @param int $offset
   * @param string $database_name
   *   The database.
   */
  public function search(string $collection_name, array $vector_input, array $output_fields, string $filters = '', int $limit = 10, int $offset = 0, string $database_name = ''): array {

    $params = [
      'collectionName' => $collection_name,
      'data' => [$vector_input],
      'annsField' => 'vector',
      'outputFields' => $output_fields,
      'limit' => $limit,
      'offset' => $offset,
    ];

    if ($database_name && !$this->isZilliz()) {
      $params['dbName'] = $database_name;
    }

    if ($filters !== '') {
      $params['filter'] = $filters;
    }

    $response = $this->makeRequest('vectordb/entities/search', [], 'POST', $params);
    $decodedResponse = json_decode($response, true);
    return is_array($decodedResponse) ? $decodedResponse : [];
  }

  /**
   * Make Milvus call.
   *
   * @param string $path
   *   The path.
   * @param array $query_string
   *   The query string.
   * @param string $method
   *   The method.
   * @param string $body
   *   Data to attach if POST/PUT/PATCH.
   * @param array $options
   *   Extra headers.
   *
   * @return string|object
   *   The return response.
   */
  protected function makeRequest($path, array $query_string = [], $method = 'GET', $body = '', array $options = []) {
    if (!$this->baseUrl) {
      throw new \Exception('No base url set.');
    }

    $headers = [
      'Content-Type' => 'application/json',
      'Accept' => 'application/json',
    ];
    if ($this->apiKey) {
      $headers['Authorization'] = 'Bearer ' . $this->apiKey;
    }

    $url = rtrim($this->baseUrl . ':' . $this->port, '/') . '/v2/' . $path;
    if ($query_string) {
      $url .= '?' . http_build_query($query_string);
    }

    $request_options = [
      'method' => strtoupper($method),
      'headers' => $headers,
      'timeout' => 120,
    ];
    if ($body !== '' && $body !== NULL) {
      $request_options['data'] = is_string($body) ? $body : json_encode($body);
    }

    $response = backdrop_http_request($url, $request_options);
    $status = isset($response->code) ? (int) $response->code : 0;
    $raw_data = $response->data ?? '';
    $data = json_decode($raw_data, TRUE);
    if (!empty($response->error) || $status >= 400) {
      $message = !empty($response->error) ? $response->error : '';
      if (is_array($data)) {
        $message = $data['message'] ?? $data['error'] ?? $message;
      }
      throw new \Exception('Milvus request failed' . ($message ? ': ' . $message : ' (HTTP ' . $status . ')'));
    }
    if ($raw_data !== '' && !is_array($data) && json_last_error() !== JSON_ERROR_NONE) {
      throw new \Exception('Milvus returned invalid JSON: ' . json_last_error_msg());
    }
    return $response->data ?? '';
  }

  /**
   * Check if we are running on zilliz.
   *
   * @return bool
   *   If we are running on zilliz.
   */
  public function isZilliz(): bool {
    // The base url could either contain zillizcloud.com or cloud.zilliz.com.
    return preg_match('(zillizcloud.com|cloud.zilliz.com)', $this->baseUrl) === 1;
  }

  /**
   * Delete entities by expression.
   *
   * @param string $collection_name
   *   The collection.
   * @param string $expr
   *   The expression to filter which entities to delete.
   * @param string $database_name
   *   The database name.
   *
   * @return array
   *   The response.
   */
  public function deleteByExpr(string $collection_name, string $expr, string $database_name = 'default'): array {
    $params = [
      'collectionName' => $collection_name,
      'filter'         => $expr,
    ];
    if ($database_name && !$this->isZilliz()) {
      $params['dbName'] = $database_name;
    }
    $decoded = json_decode($this->makeRequest('vectordb/entities/delete', [], 'POST', $params), TRUE);
    return is_array($decoded) ? $decoded : [];
  }


}
