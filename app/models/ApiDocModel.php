<?PHP

/**
 * This Class help to:
 *  Load Api Config, with pull data from metadata
 *  Generate Api Doc
 */
class ApiDocModel
{
    const CONFIG_PATH                  = 'path';
    const CONFIG_DESCRIPTION           = 'description';
    const CONFIG_OPERATIONS            = 'operations';
    const CONFIG_METHOD                = 'method';
    const CONFIG_ACCESS                = 'access';
    const CONFIG_AUTHENTICATION        = 'authentication';
    const CONFIG_ACCESS_RIGHT_CODE     = 'access_right_code';
    const CONFIG_SUMMARY               = 'summary';
    const CONFIG_NOTES                 = 'notes';
    const CONFIG_RETURN                = 'return';
    const CONFIG_RETURN_TYPE           = 'return_type';
    const CONFIG_RETURN_WRAPPER        = 'data';
    const CONFIG_PARAMETERS            = 'parameters';
    const CONFIG_PARAMETERS_LABEL      = 'parameters_label';
    const CONFIG_PARAMETERS_FORMAT     = 'parameters_format';
    const CONFIG_PARAMETERS_BODY_TYPE  = 'parameters_body_type';
    const CONFIG_PARAMETERS_BODY_METADATA = 'parameters_body_metadata';
    const CONFIG_VALIDATIONS           = 'validations';
    const CONFIG_PARAM_TYPE            = 'param_type';
    const CONFIG_METADATA              = 'metadata';
    const CONFIG_ERROR_RESPONSES       = 'error_responses';
    const CONFIG_DB_COUNTRY_CODE       = 'db_country_code';
    const CONFIG_RESPONSE_FILTER       = 'response_filter';
    const CONFIG_TARGET_EXECUTION_TIME = 'target_execution_time';
    const CONFIG_TARGET_CACHE_REQUEST_COUNT = 'target_cache_request_count';
    const CONFIG_VALIDATE_PUT_REQUIRED = 'validate_put_required';
    const CONFIG_LOCALE                = 'locale';

    const PARAM_TYPE_PATH   = 'path';
    const PARAM_TYPE_QUERY  = 'query';
    const PARAM_TYPE_HEADER = 'header';
    const PARAM_TYPE_FORM   = 'form';
    const PARAM_TYPE_BODY   = 'body';

    const RETURN_TYPE_LISTING = 'listing'; // {'data' => [{<return>}], 'pagination' => {...}}
    const RETURN_TYPE_OBJECT  = 'object';  // {'data' => {<return>}}
    const RETURN_TYPE_ARRAY   = 'array';   // {'data' => [{<return>}]}
    const RETURN_TYPE_PAYLOAD = 'payload'; // {'payload' => { 'type' => '<type>', 'content' => {<return>} }}

    const CACHE_TAG     = 'api';
    const CACHE_MINUTES = 1440;


    //Add useless property here
    private $abc = 0;

    /**
     * Configuration Content.
     * Example: $this->config_content[<config cache key>] = array(<content in config file>)
     * @var array
     */
    private $config_content = array();

    /**
     * Config Error.
     * @var array
     */
    private $config_errors = array();

    /**
     * Configuration Name; Name always refer to file name in api configuration folder.
     * @var string
     */
    private $config_name = '';

    /**
     * Path to api configuration
     * @static
     * @var string
     */
    public static $api_config_path = 'apis/';

    /**
     * 1: Use format Mapping 2: Complete Sets
     * @var integer
     */
    private $return_format_associated = 1;

    /**
     * Swagger Model
     * @var array
     */
    private $swagger_model = array();

    /**
     * Swagger Definition
     * @var array
     */
    private $swagger_definition = array();

    /**
     * Construct Object.
     */
    public function __construct()
    {

    }

    /**
     * Get Config
     * @param string $config_name config name
     * @param string $operation operation name
     * @param string $node specifice node; i.e: path, method, etc.
     * @return array
     */
    public function getConfig($config_name, $operation)
    {
        $this->config_name = $config_name;

        //Load the complete config
        return $this->loadConfig($operation);
    }

    /**
     * Get Raw Config. Complete means entire entity.
     * @param string $config_name config name
     * @return array
     */
    public function getRawConfig($config_name)
    {
        $this->config_name = $config_name;

        //Get Content
        $api_config_path = self::$api_config_path . $this->config_name;
        $config_content = Config::get($api_config_path);

        //Attach Parameters with metadata
        $this->attachInfo($config_content);

        //Validate Api Config
        $this->validateApiConfig($config_content);

        return $config_content;
    }

    private function validateApiConfig($config_content)
    {
        if (empty($config_content)) {

            return;
        }

        foreach ($config_content as $path => $path_config) {

            if (empty($path_config[self::CONFIG_DESCRIPTION])) {

                $this->config_errors[] = array(
                    'path' => $path,
                    'message' => 'Missing element: ' . self::CONFIG_DESCRIPTION,
                );
            }

            if (empty($path_config[self::CONFIG_OPERATIONS])) {

                $this->config_errors[] = array(
                    'path' => $path,
                    'message' => 'Missing element: ' . self::CONFIG_OPERATIONS,
                );
            } else {

                foreach ($path_config[self::CONFIG_OPERATIONS] as $operation => $operation_config) {

                    $this->validateApiOperation($path, $operation, $operation_config);
                }
            }
        }
    }

    private function validateApiOperation($path, $operation, $operation_config)
    {
        if (empty($operation_config[self::CONFIG_METHOD])) {

            $this->config_errors[] = array(
                'operation' => $path . '.' . $operation,
                'message' => 'Missing element: ' . self::CONFIG_METHOD,
            );
        }

        if (empty($operation_config[self::CONFIG_SUMMARY])) {

            $this->config_errors[] = array(
                'operation' => $path . '.' . $operation,
                'message' => 'Missing element: ' . self::CONFIG_SUMMARY,
            );
        }

        if (!empty($operation_config[self::CONFIG_PARAMETERS])) {

            $this->validateApiParam($path, $operation, $operation_config[self::CONFIG_PARAMETERS]);
        }
    }

    private function validateApiParam($path, $operation, $param_config)
    {
        if (empty($param_config)) {

            return;
        }

        foreach ($param_config as $param_type => $params) {

            if ($param_type != self::PARAM_TYPE_PATH
                && $param_type != self::PARAM_TYPE_QUERY
                && $param_type != self::PARAM_TYPE_HEADER
                && $param_type != self::PARAM_TYPE_FORM
                && $param_type != self::PARAM_TYPE_BODY) {

                $this->config_errors[] = array(
                    'param' => $path . '.' . $operation . '.' . $param_type,
                    'message' => 'Invalid parameter type: ' . $param_type,
                );
            }
        }
    }

    /**
     * Get Error Found during process api config
     * @return array
     */
    public function getConfigErrors()
    {
        return $this->config_errors;
    }

    /**
     * Flush Config Cache Tag
     * @return void
     */
    public function flushConfigCache()
    {
        CacheModel::removeCacheByTag(self::CACHE_TAG);
    }

    private function loadConfig($operation)
    {
        //Retrieve Cache Key
        $cache_key = $this->getCacheKey($this->config_name, $operation);

        //Try to get from class property
        if (isset($this->config_content[$cache_key]) && !empty($this->config_content[$cache_key])) {

            return $this->config_content[$cache_key];
        }

        //Try to get from cache
        $config_content = CacheModel::getCache(self::CACHE_TAG, $cache_key, true, false);
        if (!empty($config_content)) {

            $this->config_content[$cache_key] = $config_content;
            return $this->config_content[$cache_key];
        }

        //Get Complete Config from Config File
        $config_content = $this->getRawConfig($this->config_name);
        if (empty($config_content)) {

            return false;
        }

        //Loop Thru and Set all configuration based on operations
        foreach ($config_content as $path => $path_config) {

            if (!isset($path_config[self::CONFIG_OPERATIONS]) || !is_array($path_config[self::CONFIG_OPERATIONS])) {

                continue;
            }

            foreach ($path_config[self::CONFIG_OPERATIONS] as $operation_name => $operation_config) {

                $operation_content = array();

                if (!empty($path_config[self::CONFIG_DB_COUNTRY_CODE])
                    && $path_config[self::CONFIG_DB_COUNTRY_CODE] == true) {

                    $operation_content[self::CONFIG_PATH] = '{db_country_code}/' . $path;
                } else {

                    $operation_content[self::CONFIG_PATH] = $path;
                }

                $operation_content += $operation_config;

                //Handle Parameter Format
                if (isset($operation_content[self::CONFIG_PARAMETERS])) {

                    foreach ($operation_content[self::CONFIG_PARAMETERS] as $param_type => $params) {

                        $parameters = $operation_content[self::CONFIG_PARAMETERS][$param_type];

                        $operation_content[self::CONFIG_PARAMETERS_LABEL][$param_type] = array();
                        $operation_content[self::CONFIG_PARAMETERS_FORMAT][$param_type] = array();
                        $operation_content[self::CONFIG_PARAMETERS][$param_type] = array();

                        if ($param_type == self::PARAM_TYPE_BODY) {

                            // Set 'data' envelop for body (payload) if body type is set
                            $body_type = '';
                            if (isset($operation_content[self::CONFIG_PARAMETERS_BODY_TYPE])) {

                                $body_type = $operation_content[self::CONFIG_PARAMETERS_BODY_TYPE];

                                // Validation
                                if (!in_array($body_type, ['object', 'array'])) {

                                    $debug_msg = sprintf(
                                        "%s %s: API config 'parameters_body_type' is invalid [object|array]",
                                        strtoupper($operation_content[self::CONFIG_METHOD]),
                                        $path
                                    );
                                    throw new ExceptionModel('error.internal_error', 500, null, $debug_msg);
                                }
                            }

                            $key_prefix = '';
                            if ($body_type == 'object') {

                                $key_prefix = 'data.';
                            } elseif ($body_type == 'array') {

                                $key_prefix = 'data.0.';
                            }

                            $this->getParameterFormatBody(
                                $parameters,
                                $operation_content[self::CONFIG_PARAMETERS][$param_type],
                                $operation_content[self::CONFIG_PARAMETERS_LABEL][$param_type],
                                $operation_content[self::CONFIG_PARAMETERS_FORMAT][$param_type],
                                $key_prefix
                            );

                            if (isset($operation_content[self::CONFIG_PARAMETERS_BODY_METADATA])) {

                                // Validation
                                if (empty($operation_content[self::CONFIG_PARAMETERS_BODY_TYPE])) {

                                    $debug_msg = sprintf(
                                        "%s %s: Please specify 'parameters_body_type' [object|array]",
                                        strtoupper($operation_content[self::CONFIG_METHOD]),
                                        $path
                                    );
                                    throw new ExceptionModel('error.internal_error', 500, null, $debug_msg);
                                }

                                $parameters = $operation_content[self::CONFIG_PARAMETERS_BODY_METADATA];

                                unset($operation_content[self::CONFIG_PARAMETERS_BODY_METADATA]);

                                $this->getParameterFormatBody(
                                    $parameters,
                                    $operation_content[self::CONFIG_PARAMETERS][$param_type],
                                    $operation_content[self::CONFIG_PARAMETERS_LABEL][$param_type],
                                    $operation_content[self::CONFIG_PARAMETERS_FORMAT][$param_type]
                                );
                            }
                        } else {

                            $this->getParameterFormatQuery(
                                $parameters,
                                $operation_content[self::CONFIG_PARAMETERS][$param_type],
                                $operation_content[self::CONFIG_PARAMETERS_LABEL][$param_type],
                                $operation_content[self::CONFIG_PARAMETERS_FORMAT][$param_type]
                            );
                        }
                    }
                }

                //Handle Return Format
                if (isset($operation_content[self::CONFIG_RETURN])) {

                    $return_type = '';
                    if (isset($operation_content[self::CONFIG_RETURN_TYPE])) {

                        $return_type = $operation_content[self::CONFIG_RETURN_TYPE];
                    }

                    $this->getReturnFormat($operation_content[self::CONFIG_RETURN], 1, $return_type);
                }

                $operation_cache_key = $this->getCacheKey($this->config_name, $operation_name);
                CacheModel::setCache(
                    self::CACHE_TAG,
                    $operation_cache_key,
                    $operation_content,
                    self::CACHE_MINUTES,
                    true,
                    false
                );

                $this->config_content[$operation_cache_key] = $operation_content;
            }
        }

        //If set, then return
        if (isset($this->config_content[$cache_key])) {

            return $this->config_content[$cache_key];
        }

        return false;
    }

    /**
     * Get the parameter for body and set the labels, rules, formats.
     * This info will be used in api_config.
     *
     * @param array  $params      Fields from metadata
     * @param array  $new_rules   Rules, example array('work_locations.0.code' => 'required|integer')
     * @param array  $new_labels  Labels, example array('work_locations.0.code' => 'Work Location Code')
     * @param array  $new_formats Formats, example array('work_locations.0.code' => 'integer')
     * @param string $key_prefix  Use in recursive call to append in the array key
     * @return void
     */
    public function getParameterFormatBody($params, &$new_rules, &$new_labels, &$new_formats, $key_prefix = '')
    {
        if (empty($params)) {

            return;
        }

        foreach ($params as $field => $metadata) {

            /**
             * Validation is NOT needed for those primary_key == false,
             *  because those are fields from other metadata/entity, mostly reference data.
             */
            if (isset($metadata[MetadataModel::ELEMENT_PRIMARY_KEY])
                && $metadata[MetadataModel::ELEMENT_PRIMARY_KEY] === false) {

                continue;
            }

            // Retrieve those metadata details
            $rules = '';
            if (! empty($metadata[MetadataModel::ELEMENT_VALIDATION_RULES])) {

                $rules = $metadata[MetadataModel::ELEMENT_VALIDATION_RULES];
            }
            $type = empty($metadata[MetadataModel::ELEMENT_TYPE])?'':$metadata[MetadataModel::ELEMENT_TYPE];
            $label = empty($metadata[MetadataModel::ELEMENT_LABEL])?$field:$metadata[MetadataModel::ELEMENT_LABEL];
            $format = $this->getParameterValidationFormat($metadata);

            if ($type == MetadataModel::TYPE_SCALAR) {

                $tmp_key = $key_prefix . $field;
                $new_rules[$tmp_key] = $rules;
                $new_labels[$tmp_key] = $label;
                $new_formats[$tmp_key] = $format;
            } elseif ($type == MetadataModel::TYPE_ARRAY) {

                // Handle if there is array validation
                $rules_array = ValidationModel::getRulesForArray($rules);

                $tmp_key = $key_prefix . $field;
                if (!empty($rules_array)) {

                    $new_rules[$tmp_key] = $rules_array;
                    $new_labels[$tmp_key] = $label;
                }

                $tmp_key .= '.0';
                $new_rules[$tmp_key] = $rules;
                $new_labels[$tmp_key] = $label;
                $new_formats[$tmp_key] = $format;
            } elseif (! empty($metadata[MetadataModel::ELEMENT_FIELDS])) {

                $fields = $metadata[MetadataModel::ELEMENT_FIELDS];

                if ($type == MetadataModel::TYPE_OBJECT) {

                    $tmp_key_prefix = $key_prefix . $field . '.';
                } elseif ($type == MetadataModel::TYPE_ARRAY_OF_OBJECT) {

                    // Handle if there is array validation
                    $rules_array = ValidationModel::getRulesForArray($rules);
                    if (! empty($rules_array)) {

                        $tmp_key = $key_prefix . $field ;
                        $new_rules[$tmp_key] = $rules_array;
                        $new_labels[$tmp_key] = $label;
                    }

                    $tmp_key_prefix = $key_prefix . $field . '.0.';
                }

                $this->getParameterFormatBody($fields, $new_rules, $new_labels, $new_formats, $tmp_key_prefix);
            }
        }
        return;
    }

    /**
     * Get the parameter for queuy/header/form/path and set the labels, rules, formats.
     * It is different with body; For query string, we just want everything in scalar
     * This info will be used in api_config.
     *
     * @param array $params fields from metadata
     * @param array $new_rules rules, example array('work_locations.0.code' => 'required|integer')
     * @param array $new_labels labels, example array('work_locations.0.code' => 'Work Location Code')
     * @param array $new_formats formats, example array('work_locations.0.code' => 'integer')
     * @return void
     */
    public function getParameterFormatQuery($params, &$new_rules, &$new_labels, &$new_formats)
    {
        if (empty($params)) {

            return;
        }

        foreach ($params as $field => $metadata) {

            // Retrieve those metadata details
            $rules = '';
            if (! empty($metadata[MetadataModel::ELEMENT_VALIDATION_RULES])) {

                $rules = $metadata[MetadataModel::ELEMENT_VALIDATION_RULES];
            }
            $format = $this->getParameterValidationFormat($metadata);
            $label = empty($metadata[MetadataModel::ELEMENT_LABEL])?$field:$metadata[MetadataModel::ELEMENT_LABEL];
            $type = empty($metadata[MetadataModel::ELEMENT_TYPE])?'':$metadata[MetadataModel::ELEMENT_TYPE];

            if ($type == MetadataModel::TYPE_SCALAR) {

                $new_rules[$field] = $rules;
                $new_labels[$field] = $label;
                $new_formats[$field] = $format;
            } elseif ($type == MetadataModel::TYPE_ARRAY) {

                //If array, unset all the array rules, then regenerate the rules
                if (isset($metadata[MetadataModel::ELEMENT_VALIDATIONS])) {

                    $validation = $metadata[MetadataModel::ELEMENT_VALIDATIONS];
                    unset($validation['asize']);
                    unset($validation['abetween']);
                    unset($validation['amin']);
                    unset($validation['amax']);
                    $obj_validation = new ValidationModel();
                    $new_rules[$field] = $obj_validation->getValidationRules($validation);
                    $new_labels[$field] = $label;
                    $new_formats[$field] = $format;
                }
            } elseif (! empty($metadata[MetadataModel::ELEMENT_FIELDS])) {

                //If object / array of object, attach with the PK, and its rules
                foreach ($metadata[MetadataModel::ELEMENT_FIELDS] as $metadata2) {

                    if (isset($metadata2[MetadataModel::ELEMENT_PRIMARY_KEY])
                        && $metadata2[MetadataModel::ELEMENT_PRIMARY_KEY] == true
                        && isset($metadata2[MetadataModel::ELEMENT_VALIDATION_RULES])) {

                        $new_rules[$field] = $metadata2[MetadataModel::ELEMENT_VALIDATION_RULES];
                        $new_labels[$field] = $label;
                        $new_formats[$field] = $this->getParameterValidationFormat($metadata2);
                    }
                }
            }
        }
        return;
    }

    private function getParameterValidationFormat($field_metadata)
    {
        $field_format = '';

        if (!empty($field_metadata[MetadataModel::ELEMENT_VALIDATIONS]['format'])) {

            $field_format = $field_metadata[MetadataModel::ELEMENT_VALIDATIONS]['format'];
        }

        if (isset($field_metadata[MetadataModel::ELEMENT_VALIDATIONS]['csv'])
            && $field_metadata[MetadataModel::ELEMENT_VALIDATIONS]['csv'] == true) {

            $field_format = trim('csv|' . $field_format, '|');
        }

        return $field_format;
    }

    /**
     * Get Return Format. Support Format:	 *
     *
     * @param array   $return
     * @param integer $return_format_associated 1:data type 2: complete set
     * @param string  $return_type
     * @return void
     *
     * @throws ExceptionModel
     */
    private function getReturnFormat(&$return, $return_format_associated = 1, $return_type = '')
    {
        if (empty($return)) {

            return;
        }

        if ($return_format_associated == 2) {

            return;
        }

        // Loop for each return
        $new_return = array();
        $etype = MetadataModel::ELEMENT_TYPE;
        $efield = MetadataModel::ELEMENT_FIELDS;

        foreach ($return as $field => $field_metadata) {

            if (!isset($field_metadata[$etype])) {

                throw new ExceptionModel('error.internal_error', 500, null, 'Type not defined in metadata '. $field);
            }

            if ($field_metadata[$etype] == MetadataModel::TYPE_SCALAR) {

                $this->getReturnFormatValue($new_return[$field], $field_metadata, $field);
            } elseif ($field_metadata[$etype] == MetadataModel::TYPE_ARRAY) {

                $this->getReturnFormatValue($new_return[$field][], $field_metadata, $field);
            } elseif ($field_metadata[$etype] == MetadataModel::TYPE_ARRAY_OF_OBJECT
                     || $field_metadata[$etype] == MetadataModel::TYPE_OBJECT) {

                if (!empty($field_metadata[$efield])) {

                    $this->getReturnFormat(
                        $return[$field][$efield],
                        $return_format_associated
                    );

                    if ($field_metadata[$etype] == MetadataModel::TYPE_ARRAY_OF_OBJECT) {

                        $new_return[$field][] = $return[$field][$efield];
                    } else {

                        $new_return[$field] = $return[$field][$efield];
                    }
                } else {

                    $this->getReturnFormatValue($new_return[$field], $field_metadata, $field);
                }
            }
        }

        // Handling for each return type
        switch ($return_type) {
            case self::RETURN_TYPE_LISTING:
                $new_return = array(
                    'paging' => array(
                        'page' => 'integer',
                        'per_page' => 'integer',
                        'total' => 'integer',
                        'previous' => 'url',
                        'next' => 'url'
                    ),
                    'data' => array($new_return),
                );
                break;
            case self::RETURN_TYPE_OBJECT:
                $new_return = array(
                    'data' => $new_return,
                );
                break;
            case self::RETURN_TYPE_ARRAY:
                $new_return = array(
                    'data' => array($new_return),
                );
                break;
            case self::RETURN_TYPE_PAYLOAD:
                $new_return = array(
                    'payload' => array(
                        'type' => 'string',
                        'content' => $new_return,
                    )
                );
                break;
        }

        $return = $new_return;
        return;
    }

    private function getReturnFormatValue(&$return, $field_metadata, $key)
    {
        if (!empty($field_metadata[MetadataModel::ELEMENT_VALIDATIONS])) {

            $return = ValidationModel::getFormat($field_metadata[MetadataModel::ELEMENT_VALIDATIONS]);
        } else {

            $return = ValidationModel::DEFAULT_FORMAT;
        }
    }

    private function attachInfo(&$config_content)
    {
        if (empty($config_content)) {

            return;
        }

        foreach ($config_content as $path => &$path_config) {

            if (empty($path_config[self::CONFIG_OPERATIONS])) {

                continue;
            }

            foreach ($path_config[self::CONFIG_OPERATIONS] as $operation_name => &$operation_config) {

                $override_validation_rule = array();
                if (! empty($operation_config[self::CONFIG_VALIDATIONS])) {

                    $override_validation_rule = $operation_config[self::CONFIG_VALIDATIONS];
                }

                if (!empty($operation_config[self::CONFIG_PARAMETERS])) {

                    foreach ($operation_config[self::CONFIG_PARAMETERS] as $param_type => &$params) {

                        $this->attachParam($params, $override_validation_rule, $param_type);
                    }
                }

                if (!empty($operation_config[self::CONFIG_RETURN])) {

                    $this->attachParam($operation_config[self::CONFIG_RETURN]);
                }

                if (!empty($operation_config[self::CONFIG_PARAMETERS_BODY_METADATA])) {

                    $this->attachParam($operation_config[self::CONFIG_PARAMETERS_BODY_METADATA]);
                }
            }
        }
    }

    public function attachParam(&$params, $override_validation_rule = array(), $param_type = '')
    {
        if (empty($params) || !is_array($params)) {

            return;
        }

        $new_params = array();

        //Handle the override validation rule
        $validation_override = array();
        if (! empty($override_validation_rule)) {

            foreach ($override_validation_rule as $field => $rule) {

                $validations = explode('|', $rule);
                foreach ($validations as $rule2) {

                    $arr_rule = explode('=', $rule2);
                    if (!empty($arr_rule[0]) && isset($arr_rule[1])) {

                        $validation_override[$field][$arr_rule[0]] = $arr_rule[1];
                    }
                }
            }
        }

        //Loop for each Param
        foreach ($params as $i => &$param) {

            $tmp_metadata = explode('.', $param);

            if (empty($tmp_metadata[0])) {

                continue;
            }

            //1st Case: Support add-on field in APIs Documentation
            if ($tmp_metadata[0] != 'metadata') {

                $ipos = stripos($param, ' ');
                $key = substr($param, 0, $ipos);
                $label = trim(substr($param, $ipos + 1));

                $new_params[$key] = array(
                    MetadataModel::ELEMENT_TYPE => MetadataModel::TYPE_SCALAR,
                    MetadataModel::ELEMENT_LABEL => $label,
                    MetadataModel::ELEMENT_VALIDATIONS => array(),
                    MetadataModel::ELEMENT_VALIDATION_RULES => '',
                );
            } elseif ($tmp_metadata[0] == 'metadata') {

                //Reset Params
                if (empty($tmp_metadata[2])) {

                } else {

                    $last_index = count($tmp_metadata) - 1;

                    // If there are nested object for non-body type of input, e.g. query string
                    // The last field will be chosen as key
                    if (!empty($param_type) && $param_type != self::PARAM_TYPE_BODY) {
                        $key = $tmp_metadata[$last_index];
                    } else {
                        $key = $tmp_metadata[2];
                    }

                    $new_key = '';

                    // Custom name, e.g. 'metadata.<entities>.siva_resume_id as resume_id'
                    if (preg_match('/(\w+) as (\w+)/', $tmp_metadata[$last_index], $matches)) {

                        $tmp_metadata[$last_index] = $matches[1]; // Original name

                        // Alias name
                        if ($last_index == 2 || (!empty($param_type) && $param_type != self::PARAM_TYPE_BODY)) {
                            $key = $matches[2];
                        } else {
                            $new_key = $matches[2];
                        }
                    }

                    $param = join('.', $tmp_metadata);
                    $metadata_content = $this->getFieldMetadataByNode($param, $new_key, $param_type);

                    if (isset($new_params[$key])) {

                        $new_params[$key] = array_replace_recursive($new_params[$key], $metadata_content);
                    } else {

                        $new_params[$key] = $metadata_content;
                    }
                }
            }
        }

        //Override Validation Rules
        if (! empty($validation_override)) {

            foreach ($validation_override as $field => $rules) {

                //Refer to the metadata
                $nodes = explode('.', $field);
                $ref = &$new_params;
                $found = true;
                foreach ($nodes as $node) {

                    if (isset($ref[MetadataModel::ELEMENT_FIELDS][$node])) {

                        $ref = &$ref[MetadataModel::ELEMENT_FIELDS][$node];
                    } elseif (isset($ref[$node])) {

                        $ref = &$ref[$node];
                    } else {

                        $found = false;
                    }
                }

                //Override the default
                if ($found) {

                    // If there are nested object for non-body type of input, e.g. query string
                    // The primary key field will be chosen for rules overriding
                    if (!empty($param_type) && $param_type != self::PARAM_TYPE_BODY && !empty($ref[MetadataModel::ELEMENT_FIELDS])) {

                        foreach ($ref[MetadataModel::ELEMENT_FIELDS] as $field => $field_config) {

                            if (isset($field_config[MetadataModel::ELEMENT_PRIMARY_KEY])
                                && $field_config[MetadataModel::ELEMENT_PRIMARY_KEY] == true
                            ) {

                                $ref = &$ref[MetadataModel::ELEMENT_FIELDS][$field];
                                break;
                            }
                        }
                    }

                    if (isset($ref[MetadataModel::ELEMENT_VALIDATIONS])) {

                        $ref[MetadataModel::ELEMENT_VALIDATIONS] = array_merge(
                            $ref[MetadataModel::ELEMENT_VALIDATIONS],
                            $rules
                        );
                    } else {

                        $ref[MetadataModel::ELEMENT_VALIDATIONS] = $rules;
                    }

                    $obj_validation = new ValidationModel();
                    $ref[MetadataModel::ELEMENT_VALIDATION_RULES] = $obj_validation->getValidationRules($ref[MetadataModel::ELEMENT_VALIDATIONS]);
                }
            }
        }
        $params = $new_params;
        return;
    }

    public function getFieldMetadataByNode($metadata_node, $new_key = '', $param_type = '')
    {
        $tmp_metadata = explode('.', $metadata_node);

        if (empty($tmp_metadata[1])) {

            #continue;
            return;
        }

        $metadata_name = $tmp_metadata[1];
        $node = MetadataModel::ELEMENT_FIELDS;
        if (! empty($tmp_metadata[2])) {

            $node .= '.' . str_replace('.', '.' . MetadataModel::ELEMENT_FIELDS . '.', $tmp_metadata[2]);
        }

        //Retrieve Metadata
        $obj_metadata = new MetadataModel();
        $metadata_content = $obj_metadata->getMetadata($metadata_name, $node, true);

        //Filter Extra Field
        if (! empty($tmp_metadata[3])) {

            $total_node = count($tmp_metadata);
            $tmp_metadata_content = &$metadata_content[MetadataModel::ELEMENT_FIELDS];

            for ($i = 3; $i < $total_node; $i++) {

                if (isset($tmp_metadata_content[$tmp_metadata[$i]])) {

                    $tmp_field = $tmp_metadata_content[$tmp_metadata[$i]];
                    $tmp_metadata_content = array();

                    if ($i == ($total_node - 1) && !empty($new_key)) {
                        $tmp_metadata[$i] = $new_key;
                    }

                    $tmp_metadata_content[$tmp_metadata[$i]] = $tmp_field;

                    if (!empty($param_type) && $param_type != self::PARAM_TYPE_BODY) {
                        $metadata_content = &$tmp_metadata_content[$tmp_metadata[$i]];
                    }

                    if ($i != ($total_node - 1)) {

                        if (isset($tmp_field[MetadataModel::ELEMENT_FIELDS])) {
                            $tmp_metadata_content = &$tmp_metadata_content[$tmp_metadata[$i]][MetadataModel::ELEMENT_FIELDS];
                        }
                    }
                } else {

                    return false;
                }
            }
        }
        return $metadata_content;
    }

    private function getCacheKey($config_name, $operation)
    {
        return $config_name . '::' . $operation;
    }

    /**
     * Get Swagger Doc Config (Cached)
     *
     * @param  string $api_doc_name
     * @param  string $api_operation_name
     * @param  bool $compact
     * @return array
     */
    public function getSwaggerDoc($api_doc_name = "", $api_operation_name = "", $compact = false)
    {
        $cache_key = __METHOD__ . '::'. $api_doc_name. '::'. $api_operation_name. '::'. $compact;
        $result = CacheModel::getCache(self::CACHE_TAG, $cache_key, true, false);

        if (empty($result)) {

            if (!empty($api_doc_name)) {

                $result = $this->getSwaggerApis($api_doc_name, $compact);

                if (!empty($api_operation_name)) {

                    $apis = empty($result['apis'])?array():$result['apis'];
                    $new_apis = array();
                    foreach ($apis as $i => $arr_path) {

                        if (empty($arr_path['operations'])) {

                            continue;
                        }

                        $new_operations = array();
                        foreach ($arr_path['operations'] as $arr_operation) {

                            if ($arr_operation['nickname'] == $api_operation_name) {

                                $new_operations[] = $arr_operation;
                            }
                        }

                        if (!empty($new_operations)) {

                            $tmp_new_apis = $result['apis'][$i];
                            $tmp_new_apis['operations'] = $new_operations;
                            $new_apis[] = $tmp_new_apis;
                        }
                    }

                    $result['apis'] = $new_apis;
                }

            } else {

                $result = $this->getSwaggerResourceListing();
            }

            CacheModel::setCache(self::CACHE_TAG, $cache_key, $result, self::CACHE_MINUTES, true, false);
        }

        return $result;
    }

    /**
     * Get Full Swagger Docs Config (Cached)
     * @return array
     */
    public function getFullSwaggerDocs()
    {

        $cache_key = __METHOD__;

        $result = CacheModel::getCache(self::CACHE_TAG, $cache_key, true, false);

        if (empty($result)) {

            $arr_api_doc = $this->getSwaggerDoc();

            $result = array();

            if (!empty($arr_api_doc['apis'])) {

                foreach ($arr_api_doc['apis'] as $api) {
                    $service = str_replace('/', '', $api['path']);

                    $result[] = array_merge(
                        array('service' => $service),
                        $this->getSwaggerDoc($service, '', true)
                    );
                }
            }

            CacheModel::setCache(self::CACHE_TAG, $cache_key, $result, self::CACHE_MINUTES, true, false);
        }

        return $result;
    }

    private function getSwaggerResourceListing()
    {
        $dir = app_path().'/config/'.self::$api_config_path;

        //Get files in API Config Directory
        $files = array();

        if (is_dir($dir)) {

            if ($dh = opendir($dir)) {

                while (($file = readdir($dh)) !== false) {

                    if ($file == '.' || $file == '..') {

                        continue;
                    }
                    $files[] = $file;
                }
                closedir($dh);
            }
        }

        $api_doc = array();
        $api_doc['apiVersion'] = $this->getApiVersion();
        $api_doc['swaggerVersion'] = $this->getSwaggerVersion();

        if (empty($files)) {

            return $api_doc;
        }

        sort($files);
        foreach ($files as $file) {

            $file = substr($file, 0, -4);

            if ($file == 'samples') {

                continue;
            }

            //Check is there any APIs available
            $config_content = Config::get(self::$api_config_path . $file);
            $has_apis = false;
            if (! empty($config_content)) {

                foreach ($config_content as $path => $path_config) {

                    if (! empty($path_config['operations'])) {

                        foreach ($path_config['operations'] as $operation => $operation_config) {

                            if (isset($operation_config[self::CONFIG_ACCESS])
                                && ($operation_config[self::CONFIG_ACCESS] == 'private'
                                || $operation_config[self::CONFIG_ACCESS] == 'public'
                                || $operation_config[self::CONFIG_ACCESS] == 'alpha'
                                || $operation_config[self::CONFIG_ACCESS] == 'server_only')) {

                                $has_apis = true;
                                break;
                            }
                        }
                    }
                }
            }

            if ($has_apis) {

                $api_doc['apis'][] = array(
                    'path'=> '/'.$file,
                    "description" => "Operations about " . $file,
                );
            }
        }

        return $api_doc;
    }

    private function getSwaggerVersion()
    {
        return '1.2';
    }

    //Used in Swagger 2.0 related functions
    private function getApiVersion()
    {
        return VERSION;
    }

    private function getSwaggerApis($api_doc_name, $compact = false)
    {
        $this->swagger_model = array();

        $api_doc = array();
        $api_doc['apiVersion'] = $this->getApiVersion();
        $api_doc['swaggerVersion'] = $this->getSwaggerVersion();

        //The Base Path is now follow the url rules.
        $api_doc['basePath'] = HelperModel::getBasePath();

        $api_doc['resourcePath'] = '/' . $api_doc_name;
        $api_doc['apis'] = array();

        $obj_api_doc = new ApiDocModel();
        $arr_config = $obj_api_doc->getRawConfig($api_doc_name);

        if (!empty($arr_config)) {

            foreach ($arr_config as $path => $path_config) {

                $tmp_api_doc = array();

                //Define Path; Either by county code or not.
                if (!empty($path_config[self::CONFIG_DB_COUNTRY_CODE])
                    && $path_config[self::CONFIG_DB_COUNTRY_CODE] == true) {

                    $tmp_api_doc['path'] = '/{db_country_code}/' . $path;
                } else {

                    $tmp_api_doc['path'] = '/' . $path;
                }

                if (!$compact) {
                    $tmp_api_doc['description'] = $path_config[self::CONFIG_DESCRIPTION];
                }

                $operations = $this->getSwaggerApisOperations($path, $path_config, $compact);

                if (!empty($operations)) {

                    $tmp_api_doc['operations'] = $operations;
                    $api_doc['apis'][] = $tmp_api_doc;
                }

            }
        }

        if (!empty($this->swagger_model) && !$compact) {

            $api_doc['models'] = $this->swagger_model;
        }

        return $api_doc;
    }

    private function getSwaggerApisOperations($path, $path_config, $compact = false)
    {
        $operations = array();
        if (!empty($path_config[self::CONFIG_OPERATIONS])) {

            foreach ($path_config[self::CONFIG_OPERATIONS] as $operation => $operation_config) {

                if (empty($operation_config[self::CONFIG_ACCESS])
                    || $operation_config[self::CONFIG_ACCESS] == 'disable') {

                    continue;
                }

                $tmp_operations = array();
                $tmp_operations['method'] = strtoupper($operation_config[self::CONFIG_METHOD]);
                $tmp_operations['nickname'] = $operation;

                if (!$compact) {
                    $tmp_operations['summary'] = ' <span style="font:italic bold 11px arial;">['
                        . strtoupper($operation_config[self::CONFIG_ACCESS])
                        . ']</span>';

                    if (!empty($operation_config[self::CONFIG_SUMMARY])) {

                        $tmp_operations['summary'] = $operation_config[self::CONFIG_SUMMARY]
                            . $tmp_operations['summary'];
                    }

                    if (!empty($operation_config[self::CONFIG_NOTES])) {

                        $tmp_operations['notes'] = $operation_config[self::CONFIG_NOTES];
                    }
                }

                if (!empty($operation_config[self::CONFIG_ERROR_RESPONSES])) {

                    $error_responses = $operation_config[self::CONFIG_ERROR_RESPONSES];
                    $tmp_operations['responseMessages'] = $this->getSwaggerErrorResponses($error_responses);
                }

                $tmp_operations['parameters'] = array();

                if (!empty($operation_config[self::CONFIG_PARAMETERS])) {

                    $config_parameters = $operation_config[self::CONFIG_PARAMETERS];
                    $model_key = $tmp_operations['method'] . ' ' . $path . ' ' . self::PARAM_TYPE_BODY;
                    $config_parameters_body_type = '';
                    $config_parameters_body_metadata = [];

                    if (isset($operation_config[self::CONFIG_PARAMETERS_BODY_TYPE])) {

                        $config_parameters_body_type = $operation_config[self::CONFIG_PARAMETERS_BODY_TYPE];

                        if (!in_array($config_parameters_body_type, ['object', 'array'])) {

                            $debug_msg = sprintf(
                                "%s %s: API config 'parameters_body_type' is invalid [object|array]",
                                $tmp_operations['method'],
                                $path
                            );
                            throw new ExceptionModel('error.internal_error', 500, null, $debug_msg);
                        }
                    }

                    if (isset($operation_config[self::CONFIG_PARAMETERS_BODY_METADATA])) {

                        $config_parameters_body_metadata = $operation_config[self::CONFIG_PARAMETERS_BODY_METADATA];

                        if (empty($config_parameters_body_type)) {

                            $debug_msg = sprintf(
                                "%s %s: Please specify 'parameters_body_type' [object|array]",
                                $tmp_operations['method'],
                                $path
                            );
                            throw new ExceptionModel('error.internal_error', 500, null, $debug_msg);
                        }
                    }

                    $tmp_operations['parameters'] += $this->getSwaggerApisParameters(
                        $config_parameters,
                        $model_key,
                        $config_parameters_body_type,
                        $config_parameters_body_metadata
                    );
                }

                $tmp_operations['parameters'][] = array(
                    'paramType' => 'header',
                    'name' => 'Time-Zone',
                    'description' => 'Timezone',
                    'dataType' => 'String',
                    'required' => false,
                    'allowMultiple' => false
                );

                if (!empty($operation_config[self::CONFIG_AUTHENTICATION])
                    && $operation_config[self::CONFIG_AUTHENTICATION] !== false) {

                    $tmp_operations['parameters'][] = array(
                        'paramType' => 'header',
                        'name' => 'Access-Token',
                        'description' => 'Access Token',
                        "dataType" => "String",
                        'required' => ($operation_config[self::CONFIG_AUTHENTICATION] === 'optional')?false:true,
                        'allowMultiple' => false,
                    );
                }

                // Servicing Country
                $tmp_operations['parameters'][] = array(
                    'paramType' => 'header',
                    'name' => 'Country',
                    'description' => 'Servicing Country',
                    'dataType' => 'String',
                    'required' => false,
                    'allowMultiple' => false
                );

                // Locale Language
                $tmp_operations['parameters'][] = array(
                    'paramType' => 'header',
                    'name' => 'Locale-Language',
                    'description' => 'Locale Language',
                    "dataType" => "String",
                    'required' => false,
                    'allowMultiple' => false
                );

                // Locale Country
                $tmp_operations['parameters'][] = array(
                    'paramType' => 'header',
                    'name' => 'Locale-Country',
                    'description' => 'Locale Country',
                    "dataType" => "String",
                    'required' => false,
                    'allowMultiple' => false
                );

                $path_module =  explode('/', $path);
                if ((!empty($operation_config[self::CONFIG_RESPONSE_FILTER])
                    && $operation_config[self::CONFIG_RESPONSE_FILTER] == true)
                    || (isset($path_module[0]) && $path_module[0] == 'references')) {

                    // Fields to Filter
                    $tmp_operations['parameters'][] = array(
                        'paramType' => 'query',
                        'name' => 'fields',
                        'description' => 'Fields to Filter',
                        "dataType" => "String",
                        'required' => false,
                        'allowMultiple' => false,
                    );

                    // Fields to Exclude
                    $tmp_operations['parameters'][] = array(
                        'paramType' => 'query',
                        'name' => 'exclude_fields',
                        'description' => 'Fields to Exclude',
                        "dataType" => "String",
                        'required' => false,
                        'allowMultiple' => false,
                    );

                    // Fields to Embed
                    $tmp_operations['parameters'][] = array(
                        'paramType' => 'query',
                        'name' => 'embeds',
                        'description' => 'Fields to Embed',
                        "dataType" => "String",
                        'required' => false,
                        'allowMultiple' => false,
                    );
                }

                if (!empty($path_config[self::CONFIG_DB_COUNTRY_CODE])
                    && $path_config[self::CONFIG_DB_COUNTRY_CODE] == true) {

                    $tmp_operations['parameters'][] = array(
                        'paramType' => 'path',
                        'name' => 'db_country_code',
                        'description' => 'DB Country Code',
                        "dataType" => "String",
                        'required' => true,
                        'allowMultiple' => false,
                    );
                }

                if (!empty($operation_config[self::CONFIG_RETURN])) {

                    $return_type = '';
                    if (isset($operation_config[self::CONFIG_RETURN_TYPE])) {

                        $return_type = $operation_config[self::CONFIG_RETURN_TYPE];
                    }

                    //Set Model Key For Return
                    $model_key = $tmp_operations['method'] . ' ' . $path;

                    $tmp_operations['type'] = $this->getSwaggerType(
                        $operation_config[self::CONFIG_RETURN],
                        $return_type,
                        $model_key
                    );
                }

                $operations[] = $tmp_operations;
            }
        }

        return $operations;
    }

    /**
     * Get Swagger APIs parameters.
     *
     * @param  string $config_parameters               Converted API config 'parameters'
     * @param  string $model_key                       Swagger model's key, e.g. PUT jobs/me/{job_id} body
     * @param  string $config_parameters_body_type     API config 'parameters_body_type', e.g. 'object'/'array'
     * @param  array  $config_parameters_body_metadata Converted API config 'parameters_body_metadata'
     * @return array
     */
    private function getSwaggerApisParameters($config_parameters, $model_key, $config_parameters_body_type, $config_parameters_body_metadata)
    {
        $parameters = array();

        if (empty($config_parameters)) {

            return $parameters;
        }

        foreach ($config_parameters as $param_type => $params) {

            // Merge parameters body & parameters body metadata
            if ($param_type == self::PARAM_TYPE_BODY) {

                $params += $config_parameters_body_metadata;
            }

            $fields = $this->getSwaggerApisParametersFields($param_type, $params, $model_key);
            if (!empty($fields)) {

                foreach ($fields as $new_fields) {

                    $parameters[] = $new_fields;
                }
            }
        }

        if (!empty($this->swagger_model[$model_key])) {

            $parameters[] = array(
                'paramType' => 'body',
                'name' => $model_key,
                'description' => '',
                'format' => '',
                'dataType' => $model_key,
                'required' => true,
            );

            // Support 'data' envelop for body (payload)
            if ($param_type == self::PARAM_TYPE_BODY && !empty($config_parameters_body_type)) {

                $properties = [];
                foreach ($this->swagger_model[$model_key]['properties'] as $field => $property) {

                    if (!array_key_exists($field, $config_parameters_body_metadata)) {

                        // No parameters body metadata field, unset it
                        unset($this->swagger_model[$model_key]['properties'][$field]);

                        // Append this under 'data' envelop
                        $properties[$field] = $property;
                    }
                }

                $this->swagger_model[$model_key]['properties']['data']['type'] = $config_parameters_body_type;
                $this->swagger_model[$model_key]['properties']['data']['uniqueItems'] = true;
                $this->swagger_model[$model_key]['properties']['data']['items']['$ref'] = "{$model_key} data";

                $this->swagger_model["{$model_key} data"]['id'] = "{$model_key} data";
                $this->swagger_model["{$model_key} data"]['properties'] = $properties;
            }
        }

        return $parameters;
    }

    private function getSwaggerApisParametersFields($param_type, $params, $model_key)
    {
        $fields = array();

        if (empty($params)) {

            return $fields;
        }

        foreach ($params as $field => $field_config) {

            if (isset($field_config[MetadataModel::ELEMENT_PRIMARY_KEY])
                && $field_config[MetadataModel::ELEMENT_PRIMARY_KEY] == false) {

                continue;
            }

            if ($param_type == self::PARAM_TYPE_BODY) {

                $this->addSwaggerModel($model_key, $field, $field_config, true);
            } else {

                list($field_name, $field_config) = $this->getSwaggerApisParameterFieldsDetails(
                    $field,
                    $field_config,
                    $param_type
                );

                $tmp_fields = array();
                $tmp_fields['paramType'] = $param_type;
                $tmp_fields['name'] = $field_name;

                if (isset($field_config[MetadataModel::ELEMENT_LABEL])) {

                    $tmp_fields['description'] = $field_config[MetadataModel::ELEMENT_LABEL];
                } else {

                    $tmp_fields['description'] = '';
                }

                if (isset($field_config[MetadataModel::ELEMENT_VALIDATIONS])) {

                    $field_validation = $field_config[MetadataModel::ELEMENT_VALIDATIONS];
                    $tmp_format = ValidationModel::getFormat($field_validation);
                    list($data_type, $format) = $this->getSwaggerDataTypeAndFormat($tmp_format);
                    $tmp_fields['dataType'] = $data_type;
                    $tmp_fields['format'] = $format;

                    //Append the description to mention about csv availability
                    if (! empty($field_validation['csv'])) {

                        $tmp_fields['description'] .= ' (Support CSV)';
                    }
                } else {

                    $tmp_fields['dataType'] = ValidationModel::DEFAULT_FORMAT;
                    $tmp_fields['format'] = ValidationModel::DEFAULT_FORMAT;
                }

                if ($param_type == self::PARAM_TYPE_PATH) {

                    $tmp_fields['required'] = true;
                } elseif (isset($field_config[MetadataModel::ELEMENT_VALIDATIONS])) {

                    $field_validation = $field_config[MetadataModel::ELEMENT_VALIDATIONS];
                    $tmp_fields['required'] = ValidationModel::getRequired($field_validation);

                    if (!empty($field_validation[MetadataModel::ELEMENT_IN])) {

                        $tmp_fields['enum'] = explode(',', $field_validation[MetadataModel::ELEMENT_IN]);
                    }
                }
                $fields[] = $tmp_fields;
            }
        }

        return $fields;
    }

    private function getSwaggerApisParameterFieldsDetails($field, $field_config, $param_type)
    {
        $field_type = MetadataModel::TYPE_SCALAR;
        if (isset($field_config[MetadataModel::ELEMENT_TYPE])) {

            $field_type = $field_config[MetadataModel::ELEMENT_TYPE];
        }

        $fields = array();
        if (isset($field_config[MetadataModel::ELEMENT_FIELDS])) {

            $fields = $field_config[MetadataModel::ELEMENT_FIELDS];
        }

        foreach ($fields as $field2 => $field_config2) {

            if (isset($field_config2[MetadataModel::ELEMENT_PRIMARY_KEY])
                && $field_config2[MetadataModel::ELEMENT_PRIMARY_KEY] == true) {

                $field_config = $field_config2;

                if ($param_type == self::PARAM_TYPE_QUERY) {

                    //Param Type = Query need to change the field name
                    if ($field_type == MetadataModel::TYPE_OBJECT) {

                        $field = $field;
                    }
                }
            }
        }

        //Todo: Support Array and Array of object in Query type parameter
        return array($field, $field_config);
    }

    private function getSwaggerErrorResponses($error_responses)
    {
        if (empty($error_responses)) {

            return '';
        }

        $ref_error = ExceptionModel::getRefError();
        $new_error_responses = array();

        foreach ($error_responses as $error_code) {

            if (!empty($ref_error[$error_code])) {

                $new_error_responses[] = array(
                    'code' => $error_code,
                    'message' => $ref_error[$error_code],
                );
            }
        }

        return $new_error_responses;
    }

    private function getSwaggerType($return, $return_type, $model_key)
    {
        $this->getReturnFormat($return, 2, $return_type);

        foreach ($return as $field_key => $field_config) {

            $this->addSwaggerModel($model_key, $field_key, $field_config);
        }

        if ($return_type == self::RETURN_TYPE_LISTING) {

            $new_model_key = $model_key . ' ' . self::RETURN_TYPE_LISTING;
            $model_pagination = 'Model Paging';

            $this->swagger_model[$new_model_key]['id'] = $new_model_key;
            $this->swagger_model[$new_model_key]['required'] = array();
            $this->swagger_model[$new_model_key]['properties'] = array();
            $this->swagger_model[$new_model_key]['properties']['data']['type'] = 'array';
            $this->swagger_model[$new_model_key]['properties']['data']['uniqueItems'] = false;
            $this->swagger_model[$new_model_key]['properties']['data']['items']['$ref'] = $model_key;

            $this->swagger_model[$new_model_key]['properties']['paging']['type'] = 'object';
            $this->swagger_model[$new_model_key]['properties']['paging']['uniqueItems'] = true;
            $this->swagger_model[$new_model_key]['properties']['paging']['items']['$ref'] = $model_pagination;

            //Add Model Pagination
            $this->addSwaggerModelPagination($model_pagination);

            return $new_model_key;
        } elseif ($return_type == self::RETURN_TYPE_OBJECT) {

            $new_model_key = $model_key . ' ' . self::RETURN_TYPE_OBJECT;

            $this->swagger_model[$new_model_key]['id'] = $new_model_key;
            $this->swagger_model[$new_model_key]['required'] = array();
            $this->swagger_model[$new_model_key]['properties'] = array();
            $this->swagger_model[$new_model_key]['properties']['data']['type'] = 'object';
            $this->swagger_model[$new_model_key]['properties']['data']['uniqueItems'] = false;
            $this->swagger_model[$new_model_key]['properties']['data']['items']['$ref'] = $model_key;

            return $new_model_key;
        } elseif ($return_type == self::RETURN_TYPE_ARRAY) {

            $new_model_key = $model_key . ' ' . self::RETURN_TYPE_ARRAY;

            $this->swagger_model[$new_model_key]['id'] = $new_model_key;
            $this->swagger_model[$new_model_key]['required'] = array();
            $this->swagger_model[$new_model_key]['properties'] = array();
            $this->swagger_model[$new_model_key]['properties']['data']['type'] = 'array';
            $this->swagger_model[$new_model_key]['properties']['data']['uniqueItems'] = false;
            $this->swagger_model[$new_model_key]['properties']['data']['items']['$ref'] = $model_key;

            return $new_model_key;
        } elseif ($return_type == self::RETURN_TYPE_PAYLOAD) {

            $new_model_key = $model_key . ' ' . self::RETURN_TYPE_PAYLOAD;
            $tmp_new_model_key = $model_key . ' ' . self::RETURN_TYPE_PAYLOAD . ' Content';

            $this->swagger_model[$new_model_key]['id'] = $new_model_key;
            $this->swagger_model[$new_model_key]['required'] = array();
            $this->swagger_model[$new_model_key]['properties'] = array();
            $this->swagger_model[$new_model_key]['properties']['payload']['type'] = 'object';
            $this->swagger_model[$new_model_key]['properties']['payload']['uniqueItems'] = true;
            $this->swagger_model[$new_model_key]['properties']['payload']['items']['$ref'] = $tmp_new_model_key;

            list($type, $format) = $this->getSwaggerDataTypeAndFormat('string');

            $this->swagger_model[$tmp_new_model_key]['id'] = $tmp_new_model_key;
            $this->swagger_model[$tmp_new_model_key]['required'] = array();
            $this->swagger_model[$tmp_new_model_key]['properties'] = array();
            $this->swagger_model[$tmp_new_model_key]['properties']['type']['format'] = $format;
            $this->swagger_model[$tmp_new_model_key]['properties']['type']['type'] = $type;
            $this->swagger_model[$tmp_new_model_key]['properties']['content']['type'] = 'object';
            $this->swagger_model[$tmp_new_model_key]['properties']['content']['uniqueItems'] = false;
            $this->swagger_model[$tmp_new_model_key]['properties']['content']['items']['$ref'] = $model_key;

            return $new_model_key;
        }

        return $model_key;
    }

    private function addSwaggerModelPagination($model_key)
    {
        $this->swagger_model[$model_key]['id'] = $model_key;
        $this->swagger_model[$model_key]['required'] = array();
        $this->swagger_model[$model_key]['properties'] = array();

        list($type, $format) = $this->getSwaggerDataTypeAndFormat('integer');
        $this->swagger_model[$model_key]['properties']['page']['format'] = $format;
        $this->swagger_model[$model_key]['properties']['page']['type'] = $type;

        $this->swagger_model[$model_key]['properties']['per_page']['format'] = $format;
        $this->swagger_model[$model_key]['properties']['per_page']['type'] = $type;

        $this->swagger_model[$model_key]['properties']['total']['format'] = $format;
        $this->swagger_model[$model_key]['properties']['total']['type'] = $type;

        list($type, $format) = $this->getSwaggerDataTypeAndFormat('url');
        $this->swagger_model[$model_key]['properties']['previous']['format'] = $format;
        $this->swagger_model[$model_key]['properties']['previous']['type'] = $type;

        $this->swagger_model[$model_key]['properties']['next']['format'] = $format;
        $this->swagger_model[$model_key]['properties']['next']['type'] = $type;

        $this->swagger_model[$model_key]['required'] = array('page', 'per_page', 'total', 'previous', 'next');
    }

    private function addSwaggerModel($model_key, $field_key, $field_config, $filter_non_pk = false)
    {
        if ($filter_non_pk
            && isset($field_config[MetadataModel::ELEMENT_PRIMARY_KEY])
            && $field_config[MetadataModel::ELEMENT_PRIMARY_KEY] == false) {

            return;
        }

        if (isset($this->swagger_model[$model_key]['id']) == false) {

            $this->swagger_model[$model_key]['id'] = $model_key;
            $this->swagger_model[$model_key]['required'] = array();
            $this->swagger_model[$model_key]['properties'] = array();
        }

        $field_type = '';
        if (!empty($field_config[MetadataModel::ELEMENT_TYPE])) {

            $field_type = $field_config[MetadataModel::ELEMENT_TYPE];
        }

        $field_format = ValidationModel::DEFAULT_FORMAT;
        if (!empty($field_config[MetadataModel::ELEMENT_VALIDATIONS])) {

            $field_format = ValidationModel::getFormat($field_config[MetadataModel::ELEMENT_VALIDATIONS]);
        }

        if (isset($field_config[MetadataModel::ELEMENT_FIELDS])) {

            if ($field_type == MetadataModel::TYPE_ARRAY_OF_OBJECT) {

                $this->swagger_model[$model_key]['properties'][$field_key]['type'] = 'array';
            } else {

                $this->swagger_model[$model_key]['properties'][$field_key]['type'] = 'object';
            }

            $sub_model_key = $model_key . ' ' . $field_key;

            $this->swagger_model[$model_key]['properties'][$field_key]['uniqueItems'] = true;
            $this->swagger_model[$model_key]['properties'][$field_key]['items']['$ref'] = $sub_model_key ;

            foreach ($field_config[MetadataModel::ELEMENT_FIELDS] as $field_key2 => $field_config2) {

                $this->addSwaggerModel($sub_model_key, $field_key2, $field_config2, $filter_non_pk);
            }
        } else {

            if (!empty($field_config[MetadataModel::ELEMENT_VALIDATIONS]['required'])) {

                $this->swagger_model[$model_key]['required'][] = $field_key;
            }

            if ($field_type == MetadataModel::TYPE_ARRAY) {

                $this->swagger_model[$model_key]['properties'][$field_key]['type'] = 'array';
                if (!empty($field_format)) {

                    list($type, $format) = $this->getSwaggerDataTypeAndFormat($field_format);
                    $this->swagger_model[$model_key]['properties'][$field_key]['items']['format'] = $format;
                    $this->swagger_model[$model_key]['properties'][$field_key]['items']['type'] = $type;
                }
            } else {

                if (!empty($field_format)) {

                    list($type, $format) = $this->getSwaggerDataTypeAndFormat($field_format);
                    $this->swagger_model[$model_key]['properties'][$field_key]['format'] = $format;
                    $this->swagger_model[$model_key]['properties'][$field_key]['type'] = $type;
                }
            }
        }
    }

    private function getSwaggerDataTypeAndFormat($format)
    {
        switch ($format) {

            case 'boolean':
                return array('boolean', 'boolean');

            case 'integer':
                return array('integer', 'int64');

            case 'numeric':
                return array('number', 'double');

            case 'string':
            case 'alpha':
            case 'alpha_dash':
            case 'alpha_num':
            case 'url':
            case 'active_url':
            case 'email':
            case 'ip':
            case 'date':
            case 'time':
            case 'datetime':
            case 'date_format':
            case 'timestamp':
            case 'xml':
                return array($format, 'string');
            case 'file':
                return array('file','');
            default:
                return array($format, '');
        }
    }

    //region Swagger 2.0 function

    /**
     * Get complete specification used by swagger 2.0
     * @return array
     */
    public function getSwagger2Spec()
    {
        $cache_key = __METHOD__ . '::swagger2-specifications';
        $result = CacheModel::getCache(self::CACHE_TAG, $cache_key, true, false);

        if (empty($result)) {

            $result = $this->getSwagger2SpecRoot();

            CacheModel::setCache(self::CACHE_TAG, $cache_key, $result, self::CACHE_MINUTES, true, false);
        }

        return $result;
    }

    /**
     * Swagger 2.0 version
     * @return string
     */
    private function getSwagger2Version()
    {
        return '2.0';
    }

    /**
     * Get the root specifications and loop through each entites for respective API path
     * @return array
     * @throws ExceptionModel
     */
    private function getSwagger2SpecRoot()
    {
        $url = HelperModel::getBasePath();
        $api_doc = array();
        $api_doc['swagger'] = $this->getSwagger2Version();
        $api_doc['info'] = array(
            'title' => 'JobStreet API',
            'version' => $this->getApiVersion()
        );
        $api_doc['host'] = parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT);

        if (!empty($port)) {
            $api_doc['host'] .= ':' . $port;
        }
        $api_doc['basePath'] = parse_url($url, PHP_URL_PATH);
        $api_doc['schemes'] = Config::get('metadata.schemes');
        $api_doc['consumes'] = array('application/json');
        $api_doc['produces'] = array('application/json');

        //Shared & Reserved Parameters by framework
        $api_doc['parameters'] = array();
        $api_doc['parameters'] = $this->getSwagger2SpecRootParameters();

        //Shared Error Responses
        $api_doc['responses'] = array();
        $api_doc['responses'] = $this->getSwagger2SpecRootResponses();

        $this->setFrameworkDefinitionObject();

        $api_doc['paths'] = array();
        $dir = app_path().'/config/'.self::$api_config_path;

        //Get files in API Config Directory
        $files = array();

        if (is_dir($dir)) {

            if ($dh = opendir($dir)) {

                while (($file = readdir($dh)) !== false) {

                    if ($file == '.' || $file == '..') {

                        continue;
                    }
                    $files[] = $file;
                }
                closedir($dh);
            }
        }

        if (empty($files)) {

            return $api_doc;
        }

        sort($files);
        $obj_api_doc = new ApiDocModel();

        foreach ($files as $file) {

            $file = substr($file, 0, -4);

            if ($file == 'samples' || $file == 'metadata') {

                continue;
            }
            $arr_config = $obj_api_doc->getRawConfig($file);

            //No configuration definition, skip to next
            if (empty($arr_config)) {

                continue;
            }

            $has_apis = false;

            foreach ($arr_config as $path => $path_config) {

                if (empty($path_config['operations'])) {

                    continue;
                }

                $path_key = '/' . $path;
                $api_doc['paths'][$path_key] = array();
                $path_has_api = false;

                foreach ($path_config['operations'] as $operation => $operation_config) {

                    if (empty($operation_config[self::CONFIG_ACCESS]) || !in_array($operation_config[self::CONFIG_ACCESS], array('private', 'public', 'alpha', 'server_only'))) {

                        continue;
                    }
                    $has_apis = true;
                    $path_has_api = true;

                    $tmp_operations = array();

                    $tmp_operations['operationId'] = $operation;
                    $tmp_operations['summary'] = ' <span style="font:italic bold 11px arial;">['
                        . strtoupper($operation_config[self::CONFIG_ACCESS])
                        . ']</span>';
                    if (!empty($operation_config[self::CONFIG_SUMMARY])) {

                        $tmp_operations['summary'] = $operation_config[self::CONFIG_SUMMARY]
                            . $tmp_operations['summary'];
                    }
                    if (!empty($operation_config[self::CONFIG_NOTES])) {

                        $tmp_operations['description'] = $operation_config[self::CONFIG_NOTES];
                    }
                    $tmp_operations['tags'] = array($file);

                    //Error Responses
                    if (!empty($operation_config[self::CONFIG_ERROR_RESPONSES])) {

                        $error_responses = $operation_config[self::CONFIG_ERROR_RESPONSES];
                        $tmp_operations['responses'] = $this->getSwagger2ErrorResponses($error_responses);
                    }

                    //Success Response
                    if (!empty($operation_config[self::CONFIG_RETURN])) {

                        $return_type = '';
                        if (isset($operation_config[self::CONFIG_RETURN_TYPE])) {

                            $return_type = $operation_config[self::CONFIG_RETURN_TYPE];
                        }

                        //Set Definition Key For Return
                        $definition_key = $operation_config[self::CONFIG_METHOD] . ' ' . $path;

                        $tmp_operations['responses']['200'] = array(
                            'description' => 'Success',
                            'schema' => array(
                                '$ref' => '#/definitions/' . $this->getSwagger2DefinitionSchema($operation_config[self::CONFIG_RETURN], $return_type, $definition_key)
                            )
                        );

                    }

                    $tmp_operations['parameters'] = array();

                    if (!empty($operation_config[self::CONFIG_PARAMETERS])) {

                        $config_parameters = $operation_config[self::CONFIG_PARAMETERS];
                        $definition_key = $operation_config[self::CONFIG_METHOD] . ' ' . $path . ' ' . self::PARAM_TYPE_BODY;
                        $config_parameters_body_type = '';
                        $config_parameters_body_metadata = [];

                        //validate parameters_body_type value
                        if (isset($operation_config[self::CONFIG_PARAMETERS_BODY_TYPE])) {

                            $config_parameters_body_type = $operation_config[self::CONFIG_PARAMETERS_BODY_TYPE];

                            if (!in_array($config_parameters_body_type, ['object', 'array'])) {

                                $debug_msg = sprintf(
                                    "%s %s: API config 'parameters_body_type' is invalid [object|array]",
                                    $tmp_operations['method'],
                                    $path
                                );
                                throw new ExceptionModel('error.internal_error', 500, null, $debug_msg);
                            }
                        }

                        //validate parameters_body_metadata value
                        if (isset($operation_config[self::CONFIG_PARAMETERS_BODY_METADATA])) {

                            $config_parameters_body_metadata = $operation_config[self::CONFIG_PARAMETERS_BODY_METADATA];

                            if (empty($config_parameters_body_type)) {

                                $debug_msg = sprintf(
                                    "%s %s: Please specify 'parameters_body_type' [object|array]",
                                    $tmp_operations['method'],
                                    $path
                                );
                                throw new ExceptionModel('error.internal_error', 500, null, $debug_msg);
                            }
                        }

                        $tmp_operations['parameters'] += $this->getSwagger2ApisParameters(
                            $config_parameters,
                            $definition_key,
                            $config_parameters_body_type,
                            $config_parameters_body_metadata
                        );

                    }

                    $tmp_operations['parameters'][] = array(
                        '$ref' => '#/parameters/Locale-Language'
                    );
                    $tmp_operations['parameters'][] = array(
                        '$ref' => '#/parameters/Locale-Country'
                    );
                    $tmp_operations['parameters'][] = array(
                        '$ref' => '#/parameters/Country'
                    );
                    $tmp_operations['parameters'][] = array(
                        '$ref' => '#/parameters/Time-Zone'
                    );
                    if (!empty($operation_config[self::CONFIG_AUTHENTICATION])
                        && $operation_config[self::CONFIG_AUTHENTICATION] !== false
                    ) {

                        if ($operation_config[self::CONFIG_AUTHENTICATION] !== 'optional') {
                            $arr_access_token = array(
                                '$ref' => '#/parameters/Access-Token'
                            );

                        } else {
                            $arr_access_token = array(
                                '$ref' => '#/parameters/Access-Token-Optional'
                            );
                        }


                        $tmp_operations['parameters'][] = $arr_access_token;
                    }

                    $path_module = explode('/', $path);
                    if ((!empty($operation_config[self::CONFIG_RESPONSE_FILTER])
                            && $operation_config[self::CONFIG_RESPONSE_FILTER] == true)
                        || (isset($path_module[0]) && $path_module[0] == 'references')
                    ) {

                        $tmp_operations['parameters'][] = array(
                            '$ref' => '#/parameters/fields'
                        );

                        $tmp_operations['parameters'][] = array(
                            '$ref' => '#/parameters/exclude_fields'
                        );

                        $tmp_operations['parameters'][] = array(
                            '$ref' => '#/parameters/embeds'
                        );
                    }

                    $api_doc['paths'][$path_key][$operation_config[self::CONFIG_METHOD]] = $tmp_operations;

                }

                if (!$path_has_api) {
                    unset($api_doc['paths'][$path_key]);
                }
            }

            if ($has_apis) {

                $api_doc['tags'][] = array(
                    'name' => $file,
                    "description" => "Operations about " . $file,
                );
            }
        }

        if (!empty($this->swagger_definition)) {

            $api_doc['definitions'] = $this->swagger_definition;
        }

        return $api_doc;

    }

    /**
     * Return the root parameters which is used by framework
     * @return array
     */
    private function getSwagger2SpecRootParameters()
    {
        $reserved_parameters = array();
        $reserved_parameters['Locale-Language'] = array(
            'name' => 'Locale-Language',
            'in' => 'header',
            'required' => false,
            'type' => 'string',
            'description' => 'Locale Language'
        );
        // TimeZone
        $reserved_parameters['Time-Zone'] = array(
            'name' => 'Time-Zone',
            'in' => 'header',
            'required' => false,
            'type' => 'string',
            'description' => 'Timezone'
        );
        // Servicing Country
        $reserved_parameters['Country'] = array(
            'name' => 'Country',
            'in' => 'header',
            'required' => false,
            'type' => 'string',
            'description' => 'Servicing Country'
        );
        // Locale Country
        $reserved_parameters['Locale-Country'] = array(
            'name' => 'Locale-Country',
            'in' => 'header',
            'required' => false,
            'type' => 'string',
            'description' => 'Locale Country'
        );
        // Access Token
        $reserved_parameters['Access-Token'] = array(
            'name' => 'Access-Token',
            'in' => 'header',
            'type' => 'string',
            'required' => true,
            'description' => 'Access Token'
        );

        // Access Token Optional
        $reserved_parameters['Access-Token-Optional'] = array(
            'name' => 'Access-Token',
            'in' => 'header',
            'type' => 'string',
            'description' => 'Access Token'
        );

        // Fields to Filter
        $reserved_parameters['fields'] = array(
            'name' => 'fields',
            'in' => 'query',
            'required' => false,
            'type' => 'array',
            'collectionFormat' => 'csv',
            'items' => array(
                'type' => 'string'
            ),
            'description' => 'Fields to Filter'
        );
        // Fields to Exclude
        $reserved_parameters['exclude_fields'] = array(
            'name' => 'exclude_fields',
            'in' => 'query',
            'required' => false,
            'type' => 'array',
            'collectionFormat' => 'csv',
            'items' => array(
                'type' => 'string'
            ),
            'description' => 'Fields to Exclude'
        );
        // Fields to Embed
        $reserved_parameters['embeds'] = array(
            'name' => 'embeds',
            'in' => 'query',
            'required' => false,
            'type' => 'array',
            'collectionFormat' => 'csv',
            'items' => array(
                'type' => 'string'
            ),
            'description' => 'Fields to Embed'
        );
        return $reserved_parameters;
    }

    /**
     * Return the root responses used across all API
     * @return array
     */
    private function getSwagger2SpecRootResponses()
    {
        $error_responses = array();
        //Bad Request
        $error_responses['Bad Request'] = $this->getResponseObject(
            'Bad Request',
            '#/definitions/Validation Error'
        );

        //Other Error Response
        $arr_errors = Config::get('error.errors');
        unset($arr_errors['400']);
        foreach ($arr_errors as $key => $value) {
            $error_responses[$value] = $this->getResponseObject(
                $value,
                '#/definitions/General Error'
            );
        }

        return $error_responses;
    }

    /**
     * Set framework standard object used in definition
     */
    private function setFrameworkDefinitionObject()
    {
        list($int_type, $int_format) = $this->getSwagger2DataTypeAndFormat('integer');
        $this->swagger_definition['Validation Error'] = array(
            'type' => 'object',
            'properties' => array(
                'code' => array(
                    'type' => $int_type,
                    'format' => $int_format
                ),
                'message' => array(
                    'type' => 'string'
                ),
                'reason' => array(
                    'type' => 'string'
                ),
                'field' => array(
                    '$ref' => '#/definitions/Validation Error Field'
                ),
            )
        );

        $this->swagger_definition['Validation Error Field'] = array(
            'type' => 'object',
            'properties' => array(
                '{field_name}' => array(
                    '$ref' => '#/definitions/Validation Error Field Rule'
                )
            )
        );

        $this->swagger_definition['Validation Error Field Rule'] = array(
            'type' => 'object',
            'properties' => array(
                '{validation_rule}' => array(
                    'type' => 'object',
                    'properties' => array(
                        'parameters' => array(
                            'type' => 'array',
                            'items' => array(
                                'type' => 'string'
                            )
                        ),
                        'message' => array(
                            'type' => 'string'
                        )
                    )
                )
            )
        );

        $this->swagger_definition['General Error'] = array(
            'type' => 'object',
            'properties' => array(
                'code' => array(
                    'type' => $int_type,
                    'format' => $int_format
                ),
                'message' => array(
                    'type' => 'string'
                ),
                'reason' => array(
                    'type' => 'string'
                ),
            )
        );

        list($url_type, $url_format) = $this->getSwagger2DataTypeAndFormat('url');
        $this->swagger_definition['Model Paging'] = array(
            'type' => 'object',
            'properties' => array(
                'page' => array(
                    'format' => $int_format,
                    'type' => $int_type
                ),
                'per_page' => array(
                    'format' => $int_format,
                    'type' => $int_type
                ),
                'total' => array(
                    'format' => $int_format,
                    'type' => $int_type
                ),
                'previous' => array(
                    'format' => $url_format,
                    'type' => $url_type
                ),
                'next' => array(
                    'format' => $url_format,
                    'type' => $url_type
                )
            ),
            'required' => array(
                'page',
                'per_page',
                'total',
                'previous',
                'next'
            )
        );
    }

    /**
     * To get the response object format
     * @param $description
     * @param $ref
     * @return array
     */
    private function getResponseObject($description, $ref)
    {
        return array(
            'description' => $description,
            'schema' => array(
                '$ref' => $ref,
            )
        );
    }

    /**
     * Swagger 2.0 Usage
     * Retrieve Error Responses for the API
     * @param $error_responses
     * @return array
     */
    private function getSwagger2ErrorResponses($error_responses)
    {
        if (empty($error_responses)) {

            return array();
        }

        $ref_error = ExceptionModel::getRefError();
        $new_error_responses = array();

        foreach ($error_responses as $error_code) {

            if (!empty($ref_error[$error_code])) {

                $new_error_responses[$error_code] = array(
                    '$ref' => '#/responses/' . $ref_error[$error_code]
                );
            }
        }

        return $new_error_responses;
    }

    /**
     * Get Swagger 2.0 APIs parameters.
     *
     * @param  string $config_parameters Converted API config 'parameters'
     * @param  string $model_key Swagger model's key, e.g. PUT jobs/me/{job_id} body
     * @param  string $config_parameters_body_type API config 'parameters_body_type', e.g. 'object'/'array'
     * @param  array $config_parameters_body_metadata Converted API config 'parameters_body_metadata'
     * @return array
     */
    private function getSwagger2ApisParameters($config_parameters, $model_key, $config_parameters_body_type, $config_parameters_body_metadata)
    {
        $parameters = array();

        if (empty($config_parameters)) {

            return $parameters;
        }

        foreach ($config_parameters as $param_type => $params) {

            // Merge parameters body & parameters body metadata
            if ($param_type == self::PARAM_TYPE_BODY) {

                $params += $config_parameters_body_metadata;
            }

            $fields = $this->getSwagger2ApisParametersFields($param_type, $params, $model_key);
            if (!empty($fields)) {

                foreach ($fields as $new_fields) {

                    $parameters[] = $new_fields;
                }
            }
        }

        //for model/definition
        if (!empty($this->swagger_definition[$model_key])) {

            $parameters[] = array(
                'in' => 'body',
                'name' => 'body',
                'schema' => array(
                    '$ref' => '#/definitions/' . $model_key
                ),
                'required' => true,
            );

            // Support 'data' envelop for body (payload)
            if ($param_type == self::PARAM_TYPE_BODY && !empty($config_parameters_body_type)) {

                $properties = [];
                foreach ($this->swagger_definition[$model_key]['properties'] as $field => $property) {

                    if (!array_key_exists($field, $config_parameters_body_metadata)) {

                        // No parameters body metadata field, unset it
                        unset($this->swagger_definition[$model_key]['properties'][$field]);

                        // Append this under 'data' envelop
                        $properties[$field] = $property;
                    }
                }

                $this->swagger_definition[$model_key]['properties']['data']['type'] = $config_parameters_body_type;
                $this->swagger_definition[$model_key]['properties']['data']['uniqueItems'] = true;
                if ($config_parameters_body_type == 'object') {
                    $this->swagger_definition[$model_key]['properties']['data']['$ref'] = '#/definitions/' . "{$model_key} data";
                } else {
                    $this->swagger_definition[$model_key]['properties']['data']['items']['$ref'] = '#/definitions/' . "{$model_key} data";
                }


                $this->swagger_definition["{$model_key} data"]['title'] = "{$model_key} data";
                $this->swagger_definition["{$model_key} data"]['type'] = "object";
                $this->swagger_definition["{$model_key} data"]['properties'] = $properties;
            }
        }

        return $parameters;
    }

    /**
     * Get the fields for the parameter in the definitions
     * @param $param_type       Parameter type
     * @param $params           Parameter details
     * @param $definition_key   Definition key used for the parameter
     * @return array
     */
    private function getSwagger2ApisParametersFields($param_type, $params, $definition_key)
    {
        $fields = array();

        if (empty($params)) {

            return $fields;
        }

        foreach ($params as $field => $field_config) {

            if (isset($field_config[MetadataModel::ELEMENT_PRIMARY_KEY])
                && $field_config[MetadataModel::ELEMENT_PRIMARY_KEY] == false
            ) {

                continue;
            }

            if ($param_type == self::PARAM_TYPE_BODY) {

                $this->addSwagger2Definition($definition_key, $field, $field_config, true);
            } else {

                list($field_name, $field_config) = $this->getSwaggerApisParameterFieldsDetails(
                    $field,
                    $field_config,
                    $param_type
                );

                if ($param_type == self::PARAM_TYPE_FORM) {
                    $param_type = 'formData';
                }

                $tmp_fields = array();
                $tmp_fields['in'] = $param_type;
                $tmp_fields['name'] = $field_name;

                if (isset($field_config[MetadataModel::ELEMENT_LABEL])) {

                    $tmp_fields['description'] = $field_config[MetadataModel::ELEMENT_LABEL];
                } else {

                    $tmp_fields['description'] = '';
                }

                if (isset($field_config[MetadataModel::ELEMENT_VALIDATIONS])) {

                    $field_validation = $field_config[MetadataModel::ELEMENT_VALIDATIONS];
                    $tmp_format = ValidationModel::getFormat($field_validation);
                    list($data_type, $format) = $this->getSwagger2DataTypeAndFormat($tmp_format);

                    //Append the description to mention about csv availability
                    if (!empty($field_validation['csv'])) {

                        $tmp_fields['type'] = 'array';
                        $tmp_fields['collectionFormat'] = 'csv';
                        $tmp_fields['items'] = array(
                            'type' => $data_type,
                            'format' => $format
                        );
                    } else {
                        $tmp_fields['format'] = $format;
                        $tmp_fields['type'] = $data_type;
                    }
                } else {

                    $tmp_fields['format'] = ValidationModel::DEFAULT_FORMAT;
                    $tmp_fields['type'] = ValidationModel::DEFAULT_FORMAT;
                }

                if ($param_type == self::PARAM_TYPE_PATH) {

                    $tmp_fields['required'] = true;
                } elseif (isset($field_config[MetadataModel::ELEMENT_VALIDATIONS])) {

                    $field_validation = $field_config[MetadataModel::ELEMENT_VALIDATIONS];
                    $tmp_fields['required'] = ValidationModel::getRequired($field_validation);

                    if (!empty($field_validation[MetadataModel::ELEMENT_IN])) {

                        $tmp_fields['enum'] = explode(',', $field_validation[MetadataModel::ELEMENT_IN]);
                    }
                }
                $fields[] = $tmp_fields;
            }
        }

        return $fields;
    }

    private function getSwagger2DefinitionSchema($return_fields, $return_type, $definition_key)
    {
        foreach ($return_fields as $field_key => $field_config) {

            $this->addSwagger2Definition($definition_key, $field_key, $field_config);
        }

        if ($return_type == self::RETURN_TYPE_LISTING) {

            $new_model_key = $definition_key . ' ' . self::RETURN_TYPE_LISTING;

            $this->swagger_definition[$new_model_key] = array(
                'title' => $new_model_key,
                'properties' => array(
                    'data' => array(
                        'type' => 'array',
                        'uniqueItems' => false,
                        'items' => array(
                            '$ref' => '#/definitions/' . $definition_key
                        )
                    ),
                    'paging' => array(
                        'uniqueItems' => true,
                         '$ref' => '#/definitions/Model Paging'
                    )
                )
            );

            return $new_model_key;
        } elseif ($return_type == self::RETURN_TYPE_OBJECT) {

            $new_model_key = $definition_key . ' ' . self::RETURN_TYPE_OBJECT;

            $this->swagger_definition[$new_model_key] = array(
                'title' => $new_model_key,
                'properties' => array(
                    'data' => array(
                        'uniqueItems' => false,
                         '$ref' => '#/definitions/' . $definition_key
                    )
                )
            );

            return $new_model_key;
        } elseif ($return_type == self::RETURN_TYPE_ARRAY) {

            $new_model_key = $definition_key . ' ' . self::RETURN_TYPE_ARRAY;

            $this->swagger_definition[$new_model_key] = array(
                'title' => $new_model_key,
                'properties' => array(
                    'data' => array(
                        'type' => 'array',
                        'uniqueItems' => false,
                        'items' => array(
                            '$ref' => '#/definitions/' . $definition_key
                        )
                    )
                )
            );

            return $new_model_key;
        } elseif ($return_type == self::RETURN_TYPE_PAYLOAD) {

            $new_model_key = $definition_key . ' ' . self::RETURN_TYPE_PAYLOAD;
            $tmp_new_model_key = $definition_key . ' ' . self::RETURN_TYPE_PAYLOAD . ' Content';

            $this->swagger_definition[$new_model_key] = array(
                'title' => $new_model_key,
                'properties' => array(
                    'payload' => array(
                        'type' => 'object',
                        'uniqueItems' => true,
                        'items' => array(
                            '$ref' => '#/definitions/' . $tmp_new_model_key
                        )
                    )
                )
            );

            list($type, $format) = $this->getSwagger2DataTypeAndFormat('string');

            $this->swagger_definition[$tmp_new_model_key] = array(
                'title' => $tmp_new_model_key,
                'properties' => array(
                    'type' => array(
                        'format' => $format,
                        'type' => $type
                    ),
                    'content' => array(
                        'uniqueItems' => false,
                         '$ref' => '#/definitions/' . $definition_key
                    )
                )
            );

            return $new_model_key;
        }

        return $definition_key;
    }

    private function addSwagger2Definition($definition_key, $field_key, $field_config, $filter_non_pk = false)
    {
        if ($filter_non_pk
            && isset($field_config[MetadataModel::ELEMENT_PRIMARY_KEY])
            && $field_config[MetadataModel::ELEMENT_PRIMARY_KEY] == false
        ) {

            return;
        }

        if (isset($this->swagger_definition[$definition_key]['title']) == false) {

            $this->swagger_definition[$definition_key]['title'] = $definition_key;
            $this->swagger_definition[$definition_key]['type'] = 'object';
            $this->swagger_definition[$definition_key]['properties'] = array();
        }

        $field_type = '';
        if (!empty($field_config[MetadataModel::ELEMENT_TYPE])) {

            $field_type = $field_config[MetadataModel::ELEMENT_TYPE];
        }

        $field_format = ValidationModel::DEFAULT_FORMAT;
        if (!empty($field_config[MetadataModel::ELEMENT_VALIDATIONS])) {

            $field_format = ValidationModel::getFormat($field_config[MetadataModel::ELEMENT_VALIDATIONS]);
        }

        if (isset($field_config[MetadataModel::ELEMENT_FIELDS])) {


            $sub_model_key = $definition_key . ' ' . $field_key;

            if ($field_type == MetadataModel::TYPE_ARRAY_OF_OBJECT) {

                $this->swagger_definition[$definition_key]['properties'][$field_key]['type'] = 'array';
                $this->swagger_definition[$definition_key]['properties'][$field_key]['items']['$ref'] = '#/definitions/' . $sub_model_key;
            } else {
                $this->swagger_definition[$definition_key]['properties'][$field_key]['$ref'] = '#/definitions/' . $sub_model_key;
            }

            $this->swagger_definition[$definition_key]['properties'][$field_key]['uniqueItems'] = true;


            foreach ($field_config[MetadataModel::ELEMENT_FIELDS] as $field_key2 => $field_config2) {

                $this->addSwagger2Definition($sub_model_key, $field_key2, $field_config2, $filter_non_pk);
            }
        } else {

            if (!empty($field_config[MetadataModel::ELEMENT_VALIDATIONS]['required'])) {

                if (!isset($this->swagger_definition[$definition_key]['required'])) {
                    $this->swagger_definition[$definition_key]['required'] = array();
                }
                $this->swagger_definition[$definition_key]['required'][] = $field_key;
            }

            if ($field_type == MetadataModel::TYPE_ARRAY) {

                $this->swagger_definition[$definition_key]['properties'][$field_key]['type'] = 'array';
                if (!empty($field_format)) {

                    list($type, $format) = $this->getSwagger2DataTypeAndFormat($field_format);
                    $this->swagger_definition[$definition_key]['properties'][$field_key]['items']['format'] = $format;
                    $this->swagger_definition[$definition_key]['properties'][$field_key]['items']['type'] = $type;
                }
            } else {

                $this->swagger_definition[$definition_key]['properties'][$field_key]['type'] = 'object';
                if (!empty($field_format)) {

                    list($type, $format) = $this->getSwagger2DataTypeAndFormat($field_format);
                    $this->swagger_definition[$definition_key]['properties'][$field_key]['format'] = $format;
                    $this->swagger_definition[$definition_key]['properties'][$field_key]['type'] = $type;
                }
            }
        }
    }

    private function getSwagger2DataTypeAndFormat($format)
    {
        switch ($format) {

            case 'boolean':
                return array('boolean', 'boolean');

            case 'integer':
                return array('integer', 'int64');

            case 'numeric':
                return array('number', 'double');

            case 'string':
            case 'alpha':
            case 'alpha_dash':
            case 'alpha_num':
            case 'url':
            case 'active_url':
            case 'email':
            case 'ip':
            case 'date':
            case 'time':
            case 'datetime':
            case 'date_format':
            case 'timestamp':
            case 'xml':
                return array('string', $format);
            case 'file':
                return array('file', '');
            default:
                return array('string', $format);
        }
    }

    //endregion
}
