<?php

/**
 * FieldsParser.
 *
 * @class FieldsParserAbstract
 * @copyright Artem Rotmistrenko
 */
abstract class FieldsParserAbstract {

  /**
   * Array with Fields collection.
   *
   * @var array
   */
  public $fields = array();

  /**
   * One-dimensional array with Field collection.
   *
   * @var array
   */
  public $field = array();

  /**
   * Filters array.
   *
   * @var array
   */
  protected $filterTypes = ['dom_list', 'dom_array', 'array', 'dom', 'text'];

  /**
   * StdClass obj.
   *
   * @var object
   */
  protected $storage;

  /**
   * Url.
   *
   * @var string
   */
  protected $startUrl;

  /**
   * @var string
   */
  static protected $xml = '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';

  /**
   * Public methods section.
   */

  /**
   * Get fields map.
   *
   * @return array
   *   map
   */
  abstract public function getMap();

  /**
   * Helper static methods.
   */

  /**
   * Get damain name from url.
   *
   * @param string $url
   *   Url.
   *
   * @return string
   *   domain
   */
  public static function getDomain($url) {
    $url = parse_url($url);
    return str_replace('www.', '', $url['host']);
  }

  /**
   * Convert domain to class name.
   *
   * @param string $domain
   *   Domain.
   *
   * @return string
   *   class name
   */
  public static function getClassFromDomain($domain) {

    if (array_key_exists($domain, self::$aliasUrl)) {
      return self::$aliasUrl[$domain];
    }
    else {
      $class = ucfirst(preg_replace_callback('/\.([a-z])/i', function ($match) {
        return strtoupper(str_replace('.', '', $match[0]));
      }, $domain));
    }
    return $class;
  }

  /**
   * Convert class name to domain.
   *
   * @param string $class
   *   Class name.
   *
   * @return string
   *   domain
   */
  public static function getDomainFromClass($class) {
    $url = preg_replace('/((?<!^)[A-Z])/', '.$1', $class);
    return strtolower($url);
  }

  /**
   * Convert html to a string.
   *
   * @param DOMNode $element
   *   Element.
   *
   * @return string
   *   html
   */
  protected static function domInnerHtml(DOMNode $element) {
    $innerHtml = "";
    $children = $element->childNodes;
    foreach ($children as $child) {
      $innerHtml .= $element->ownerDocument->saveHTML($child);
    }
    return $innerHtml;
  }

  /**
   * Edit DOMNode as string.
   *
   * @param DOMNode $element
   *   Element.
   * @param Closure $editor
   *   Editor for text.
   *
   * @return object
   *   DOMNode object with edited textContent
   */
  protected static function domEditHtml(DOMNode $element, Closure $editor) {
    if (is_callable($editor)) {
      $dom = new DOMDocument();
      $text = self::domInnerHtml($element);

      // @todo Replace libxml error suppression to validation(may be tidy).

      libxml_use_internal_errors(TRUE);

      $dom->loadHTML(self::$xml . $editor($text));

      libxml_use_internal_errors(FALSE);

      return $dom->childNodes->item(1);
    }
    else {
      return $element;
    }
  }

  /**
   * Log method.
   *
   * @param string $message
   *   Log message.
   */
  public static function log($message) {
      /** Debug implementation(depending on the environment) */
      error_log($message);
  }

  /**
   * FieldsParser constructor.
   *
   * @param string $url
   *   First page url.
   */
  public function __construct($url) {
    $this->startUrl = $url;
    $this->storage = new stdClass();
  }

  /**
   * Set virtual property.
   *
   * @param string $name
   *   Name.
   * @param mixed $value
   *   Value.
   */
  public function __set($name, $value) {
    if (!$this->storage instanceof stdClass) {
      $this->storage = new stdClass();
    }
    if (!isset($this->storage->$name)) {
      $this->storage->$name = array();
    }
    $this->storage->$name[] = $value;
  }

  /**
   * Get virtual property.
   *
   * @param string $name
   *   Name.
   *
   * @return mixed
   *   variable
   */
  public function __get($name) {
    if (isset($this->storage->$name)) {
      return end($this->storage->$name);
    }
    else {
      return FALSE;
    }
  }

  /**
   * Unset variable.
   *
   * @param string $name
   *   Name.
   */
  public function __unset($name) {
    if (isset($this->storage->$name)) {
      array_pop($this->storage->$name);
    }
  }

  /**
   * Check if virtual property isset.
   *
   * @param string $name
   *   Property name.
   *
   * @return bool
   *   check result
   */
  public function __isset($name) {
    return isset($this->storage->$name);
  }

  /**
   * Get Fields array.
   *
   * @param bool $hierarchical
   *   Result array modifier.
   *
   * @return array
   *   Fileds array
   */
  public function toArray($hierarchical = TRUE) {
    if ($hierarchical) {
      return $this->fileds;
    }
    else {
      return $this->field;
    }
  }

  /**
   * Protected methods section.
   */

  /**
   * ProcessFields.
   *
   * @param array|null $map
   *   Map.
   *
   * @return array
   *   fields array
   */
  public function processFields($map = NULL) {
    if (!$map) {
      $map = $this->getMap();
    }

    if (is_array($map) && count($map)) {
      foreach ($map as $mapKey => $mapField) {
        $this->fields[$mapKey] = $this->processField($mapKey, $mapField);
      }
    }
    return $this->fields;
  }

  /**
   * Field processing.
   *
   * @param string $name
   *   Name of the Field.
   * @param array $field
   *   Map for Field.
   * @param string $action
   *   Action.
   *
   * @return mixed
   *   Field result
   */
  public function processField($name, array $field, $action = 'parse') {
    $type = isset($field['type']) ? $field['type'] : 'Field';
    $action = isset($field['action']) ? $field['action'] : $action;
    $processFunc = $action . $type;

    if (method_exists($this, $processFunc)) {
      $result = $this->$processFunc($name, $field);

      if (!isset($field['no_filter'])) {
        $result = $this->applyFilters($processFunc, $field, $result);
      }

      $this->field[$name] = $result;

      return $result;
    }
  }

  /**
   * Apply filters to Field.
   *
   * @param string $method
   *   Processing method name.
   * @param array $field
   *   Field element.
   * @param mixed $data
   *   Result data.
   *
   * @return mixed
   *   Filtering result.
   */
  protected function applyFilters($method, array $field, $data) {

    $filterFunc = $method . 'Filter';
    $this->callFilter($data, $filterFunc);

    $types = $this->getFiltersChain($data);

    $type = array_shift($types);

    $this->callFilter($data, $this->getFilterMethod($type));
    $this->callFilter($data, $this->getFilterMethod($type, 'Filter', $method));

    @$this->callFilter($data, $this->getFilterClosure($field, $type));

    if (count($types)) {
      foreach ($types as $type) {

        if ($this->callFilter($data, $this->getFilterMethod($type, 'Convert', $method))
          || $this->callFilter($data, $this->getFilterClosure($field, 'convert', $type))
          || $this->callFilter($data, $this->getFilterMethod($type, 'Filter', $method))
          || $this->callFilter($data, $this->getFilterClosure($field, 'filter', $type))) {
          $this->callFilter($data, $this->getFilterMethod($type, 'Filter'));
        }
      }
    }

    $this->callFilter($data, $this->getFilterClosure($field));

    return $data;
  }

  /**
   * Find filter method.
   *
   * @param string $type
   *   Filter data type.
   * @param string $action
   *   Filter action.
   * @param mixed $method
   *   Processing method.
   *
   * @return bool|string
   *   Search result
   */
  protected function getFilterMethod($type, $action = 'Filter', $method = FALSE) {
    $typeClass = preg_replace_callback(
      '/\_\w/i',
      function ($matches) {
        return trim(strtoupper($matches[0]), '_');
      },
      ucfirst($type)
    );
    if ($method) {
      $filterName = $method . $typeClass . $action;
    }
    else {
      $filterName = lcfirst($typeClass) . $action;
    }

    if (method_exists($this, $filterName)) {
      return $filterName;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Find filter Closure object.
   *
   * @param array $haystack
   *   Haystack array.
   * @param string $action
   *   Filter action.
   * @param mixed $type
   *   Filter data type.
   *
   * @return bool|callable
   *   Search result
   */
  protected function getFilterClosure(array $haystack, $action = 'filter', $type = FALSE) {
    if ($type) {
      $filterKey = $type . '_' . $action;
    }
    else {
      $filterKey = $action;
    }

    if (isset($haystack[$filterKey]) && is_callable($haystack[$filterKey])) {
      return $haystack[$filterKey];
    }
    else {
      return FALSE;
    }
  }

  /**
   * Call to Filter.
   *
   * @param mixed $data
   *   Filter data.
   * @param mixed $filter
   *   Filter handler.
   *
   * @return bool
   *   Converted to boolean result.
   */
  protected function callFilter(&$data, $filter = NULL) {
    if (is_callable($filter)) {
      $data = $filter($data);
      return (bool) $data;
    }
    elseif (is_string($filter) && method_exists($this, $filter)) {
      $data = $this->$filter($data);
      return (bool) $data;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Get filter data types chain.
   *
   * @param mixed $data
   *   Filtering data.
   *
   * @return array|bool
   *   Data types chain
   */
  protected function getFiltersChain($data) {
    $dataType = gettype($data);
    switch ($dataType) {
      case 'object':
        $class = get_class($data);
        switch ($class) {
          case 'DOMNodeList':
            $type = 'dom_list';
            break;

          case 'DOMElement':
            $type = 'dom';
            break;

          default:
            $type = FALSE;
            break;
        }
        break;

      case 'array':
        if ($data[array_rand($data)] instanceof DOMNode) {
          $type = 'dom_array';
        }
        else {
          $type = 'array';
        }
        break;

      case 'string':
        $type = 'text';
        break;

      default:
        $type = FALSE;
        break;
    }
    if (in_array($type, $this->filterTypes)) {
      return array_slice($this->filterTypes, array_search($type, $this->filterTypes));
    }
    else {
      return FALSE;
    }
  }

  /**
   * DOM objects Array type filter.
   *
   * @param array $data
   *   DOM objects array.
   *
   * @return array
   *   Result array
   */
  protected function domArrayFilter(array $data) {
    if (is_array($data) && ($data[array_rand($data)] instanceof DOMNode)) {
      $data = array_map(array($this, 'domFilter'), $data);
    }
    return $data;
  }

  /**
   * DOM type filter.
   *
   * @param DOMNode $data
   *   DOM Element.
   *
   * @return object
   *   Result DOM
   */
  protected static function domFilter(DOMNode $data) {
    $data = self::domEditHtml($data, function ($text) {
      $text = preg_replace('/.*(<\/*.*[\w]+\d*.*\/*>).*/Uim', "$1\n", $text);
      return $text;
    });
    return $data;
  }

  /**
   * Text type filter.
   *
   * @param string $data
   *   Text.
   *
   * @return string
   *   Result text
   */
  protected function textFilter($data) {
    if (is_string($data)) {
      $data = preg_replace('/\n+\s*\n*/m', "\n", $data);
      return trim($data);
    }
    return $data;
  }

  /**
   * Parse field.
   *
   * @param string $name
   *   Element name.
   * @param array $element
   *   Field element.
   *
   * @return object|bool
   *   Field element
   */
  protected function parseField($name, array $element) {
    $node = $this->find($element['xpath']);
    if ($node) {
      return $node;
    }
    else {
      self::log('Field "' . $name . '" not found!');
      return FALSE;
    }
  }

  /**
   * Text type converter for parseField method.
   *
   * @param object $data
   *   Data.
   *
   * @return string
   *   result string.
   */
  protected function parseFieldTextConvert($data) {
    if ($data instanceof DOMElement) {
      return $data->nodeValue;
    }
    return FALSE;
  }

  /**
   * Parse page.
   *
   * @param string $name
   *   Element name.
   * @param array $element
   *   Field element.
   *
   * @return array|bool
   *   DOMElement collection
   */
  protected function parsePage($name, array $element) {

    $element['url'] = (empty($element['url']))
    ? $this->parseField($name, $element) : $element['url'];

    $page = $this->loadPage($element['url']);

    if ($page->code == '200') {
      $page->content = str_replace(array("<br>", "<br />"), "\r\n", $page->content);

      $dom = new DOMDocument();
      $this->dom = $dom;

      // @todo Replace libxml error suppression to validation(may be tidy).

      libxml_use_internal_errors(TRUE);

      $result = $dom->loadHTML(self::$xml . $page->content);

      libxml_use_internal_errors(FALSE);

      $elements = array();

      foreach ($element['fields'] as $fieldName => $field) {
        $elements[$fieldName] = $this->processField($fieldName, $field);
      }

      unset($this->dom);
    }
    else {
      return FALSE;
    }

    return $elements;
  }

  /**
   * Parse fieldSet.
   *
   * @param string $name
   *   Element name.
   * @param array $element
   *   Field element.
   *
   * @return array|bool
   *   result
   */
  protected function parseFieldSet($name, array $element) {
    if (is_array($element['xpath'])) {
      $fieldSet = array();
      foreach ($element['xpath'] as $xpath) {
        $field = $this->find($xpath);
        if ($field) {
          $fieldSet[] = $field;
        }
      }
      return $fieldSet;
    }
    return FALSE;
  }

  /**
   * Array type filter for parseFieldSet method.
   *
   * @param array $data
   *   Filtered data.
   *
   * @return array
   *   result array
   */
  protected function parseFieldSetArrayConvert(array $data) {
    $array = array();
    foreach ($data as $field) {
      $array[] = $field->nodeValue;
    }
    return $array;
  }

  /**
   * Text type filter for parseFieldSet method.
   *
   * @param array $data
   *   Data.
   *
   * @return string
   *   text value
   */
  protected function parseFieldSetTextConvert(array $data) {
    $text = '';
    foreach ($data as $field) {
      $text .= $field;
    }
    return $text;
  }

  /**
   * Parse Field collection.
   *
   * @param string $name
   *   Element name.
   * @param array $element
   *   Field element.
   *
   * @return array
   *   Elements collection
   */
  protected function parseFieldCollection($name, array $element) {
    $collection = [];
    $domainPrefix = $this->domainPrefix ? $this->domainPrefix : '';

    $nodeList = $this->find($element['xpath'], TRUE);
    if ($nodeList instanceof DOMNodeList && $nodeList->length > 0) {
      foreach ($nodeList as $node) {
        $attr = $node->getAttribute($element['attr']);
        if ($attr) {
          $collection[] = $domainPrefix . $attr;
        }
      }
    }

    return $collection;
  }

  /**
   * Find element.
   *
   * @param string $xpath
   *   Xpath query.
   * @param bool $list
   *   Formatting for returned value.
   *
   * @return mixed
   *   finding element('s)
   */
  protected function find($xpath, $list = FALSE) {
    $domXPath = new DOMXPath($this->dom);
    $nodeList = $domXPath->query($xpath);
    if ($list) {
      return $nodeList;
    }
    elseif ($nodeList->length > 0) {
      return $domXPath->query($xpath)->item(0);
    }
    return FALSE;
  }

  /**
   * Get page content.
   *
   * @param string $url
   *   Url.
   * @param resource $curl
   *   Curl resource.
   * @param array $opts
   *   Curl default options.
   *
   * @return object
   *   object with html page
   */
  protected function loadPage($url, $curl = NULL, array $opts = NULL) {
    $page = new stdClass();
    if (!$curl) {
      $curl = curl_init($url);
    }

    if ($opts && is_array($opts)) {
      $opts = array_merge($this->getCurlOpts(), $opts);
    }
    else {
      $opts = $this->getCurlOpts();
    }
    curl_setopt_array($curl, $opts);

    $page->content = curl_exec($curl);
    $page->code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    curl_close($curl);

    return $page;
  }

  /**
   * Curl default options.
   *
   * @return array
   *   options array
   */
  protected function getCurlOpts() {
    $ua = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/44.0.2403.89 Safari/537.36"';
    return [
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_USERAGENT => $ua,
      CURLOPT_SSL_VERIFYPEER => FALSE,
      CURLOPT_ENCODING => "UTF-8",
    ];
  }

}
