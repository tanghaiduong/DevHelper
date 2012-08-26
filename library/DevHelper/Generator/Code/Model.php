<?php
class DevHelper_Generator_Code_Model {
	public static function generate(array $addOn, DevHelper_Config_Base $config, array $dataClass) {
		$className = self::getClassName($addOn, $config, $dataClass);
		$tableName = DevHelper_Generator_Db::getTableName($config, $dataClass['name']);
		$commentAutoGeneratedStart = DevHelper_Generator_File::COMMENT_AUTO_GENERATED_START;
		$commentAutoGeneratedEnd = DevHelper_Generator_File::COMMENT_AUTO_GENERATED_END;
		
		$getFunctionName = self::generateGetDataFunctionName($addOn, $config, $dataClass);
		$countFunctionName = self::generateCountDataFunctionName($addOn, $config, $dataClass);
		
		$tableAlias = $dataClass['name'];
		if (in_array($tableAlias, array('group'))) {
			$tableAlias = '_' . $tableAlias;
		}
		
		$conditionsFields = DevHelper_Generator_File::varExport(DevHelper_Generator_Db::getConditionFields($dataClass['fields']), 2);
		$imageCode = self::generateImageCode($addOn, $config, $dataClass);
		if (!empty($imageCode)) {
			$dwClassName = DevHelper_Generator_Code_DataWriter::getClassName($addOn, $config, $dataClass);
			$getAllImageCode = <<<EOF
		\$imageSizes = XenForo_DataWriter::create('{$dwClassName}')->getImageSizes();
		foreach (\$all as &\$record) {
			\$record['images'] = array();
			foreach (\$imageSizes as \$imageSizeCode => \$imageSize) {
				\$record['images'][\$imageSizeCode] = \$this->getImageUrl(\$record, \$imageSizeCode);
			}
		}
EOF;
		} else {
			$getAllImageCode = '';
		}
		
		$contents = <<<EOF
<?php
class $className extends XenForo_Model {

	protected function _{$getFunctionName}Customized(array &\$data, array \$fetchOptions) {
		// customized processing for {$getFunctionName}() should go here
	}
	
	protected function _prepare{$dataClass['camelCase']}ConditionsCustomized(array &\$sqlConditions, array \$conditions, array &\$fetchOptions) {
		// customized code goes here
	}
	
	protected function _prepare{$dataClass['camelCase']}FetchOptionsCustomized(&\$selectFields, &\$joinTables, array \$fetchOptions) {
		// customized code goes here
	}
	
	protected function _prepare{$dataClass['camelCase']}OrderOptionsCustomized(array &\$choices, array &\$fetchOptions) {
		// customized code goes here
	}

	$commentAutoGeneratedStart

	public function getList(array \$conditions = array(), array \$fetchOptions = array()) {
		\$data = \$this->{$getFunctionName}(\$conditions, \$fetchOptions);
		\$list = array();
		
		foreach (\$data as \$id => \$row) {
			\$list[\$id] = \$row['{$dataClass['title_field']}'];
		}
		
		return \$list;
	}

	public function get{$dataClass['camelCase']}ById(\$id, array \$fetchOptions = array()) {
		\$data = \$this->{$getFunctionName}(array ('{$dataClass['id_field']}' => \$id), \$fetchOptions);
		
		return reset(\$data);
	}
	
	public function {$getFunctionName}(array \$conditions = array(), array \$fetchOptions = array()) {
		\$whereConditions = \$this->prepare{$dataClass['camelCase']}Conditions(\$conditions, \$fetchOptions);

		\$orderClause = \$this->prepare{$dataClass['camelCase']}OrderOptions(\$fetchOptions);
		\$joinOptions = \$this->prepare{$dataClass['camelCase']}FetchOptions(\$fetchOptions);
		\$limitOptions = \$this->prepareLimitFetchOptions(\$fetchOptions);

		\$all = \$this->fetchAllKeyed(\$this->limitQueryResults("
				SELECT {$tableAlias}.*
					\$joinOptions[selectFields]
				FROM `$tableName` AS {$tableAlias}
					\$joinOptions[joinTables]
				WHERE \$whereConditions
					\$orderClause
			", \$limitOptions['limit'], \$limitOptions['offset']
		), '{$dataClass['id_field']}');

$getAllImageCode

		\$this->_{$getFunctionName}Customized(\$all, \$fetchOptions);
		
		return \$all;
	}
		
	public function {$countFunctionName}(array \$conditions = array(), array \$fetchOptions = array()) {
		\$whereConditions = \$this->prepare{$dataClass['camelCase']}Conditions(\$conditions, \$fetchOptions);

		\$orderClause = \$this->prepare{$dataClass['camelCase']}OrderOptions(\$fetchOptions);
		\$joinOptions = \$this->prepare{$dataClass['camelCase']}FetchOptions(\$fetchOptions);
		\$limitOptions = \$this->prepareLimitFetchOptions(\$fetchOptions);

		return \$this->_getDb()->fetchOne("
			SELECT COUNT(*)
			FROM `$tableName` AS {$tableAlias}
				\$joinOptions[joinTables]
			WHERE \$whereConditions
		");
	}
	
	public function prepare{$dataClass['camelCase']}Conditions(array \$conditions, array &\$fetchOptions) {
		\$sqlConditions = array();
		\$db = \$this->_getDb();
		
		foreach ($conditionsFields as \$dbColumn) {
			if (!isset(\$conditions[\$dbColumn])) continue;
			
			if (is_array(\$conditions[\$dbColumn])) {
				if (!empty(\$conditions[\$dbColumn])) {
					// only use IN condition if the array is not empty (nasty!)
					\$sqlConditions[] = "{$tableAlias}.\$dbColumn IN (" . \$db->quote(\$conditions[\$dbColumn]) . ")";
				}
			} else {
				\$sqlConditions[] = "{$tableAlias}.\$dbColumn = " . \$db->quote(\$conditions[\$dbColumn]);
			}
		}
		
		\$this->_prepare{$dataClass['camelCase']}ConditionsCustomized(\$sqlConditions, \$conditions, \$fetchOptions);
		
		return \$this->getConditionsForClause(\$sqlConditions);
	}
	
	public function prepare{$dataClass['camelCase']}FetchOptions(array \$fetchOptions) {
		\$selectFields = '';
		\$joinTables = '';
		
		\$this->_prepare{$dataClass['camelCase']}FetchOptionsCustomized(\$selectFields,  \$joinTables, \$fetchOptions);

		return array(
			'selectFields' => \$selectFields,
			'joinTables'   => \$joinTables
		);
	}
	
	public function prepare{$dataClass['camelCase']}OrderOptions(array &\$fetchOptions, \$defaultOrderSql = '') {
		\$choices = array(
			
		);
		
		\$this->_prepare{$dataClass['camelCase']}OrderOptionsCustomized(\$choices, \$fetchOptions);
		
		return \$this->getOrderByClause(\$choices, \$fetchOptions, \$defaultOrderSql);
	}
	
$imageCode

	$commentAutoGeneratedEnd

}
EOF;

		return array($className, $contents);
	}
	
	public static function getClassName(array $addOn, DevHelper_Config_Base $config, array $dataClass) {
		return DevHelper_Generator_File::getClassName($addOn['addon_id'], 'Model_' . $dataClass['camelCase']);
	}
	
	protected static function generateImageCode(array $addOn, DevHelper_Config_Base $config, array $dataClass) {
		$imageField = DevHelper_Generator_Db::getImageField($dataClass['fields']);
		if ($imageField === false) {
			// no image field...
			return '';
		}
		
		$configPrefix = $config->getPrefix();
		$imagePath = "{$configPrefix}/{$dataClass['camelCase']}";
		$imagePath = strtolower($imagePath);

		$contents = <<<EOF
	public static function getImageFilePath(array \$record, \$size = 'l') {
		\$internal = self::_getImageInternal(\$record, \$size);
		
		if (!empty(\$internal)) {
			return XenForo_Helper_File::getExternalDataPath() . \$internal;
		} else {
			return '';
		}
	}
	
	public static function getImageUrl(array \$record, \$size = 'l') {
		\$internal = self::_getImageInternal(\$record, \$size);
		
		if (!empty(\$internal)) {
			return XenForo_Application::\$externalDataPath . \$internal;
		} else {
			return '';
		}
	}
	
	protected static function _getImageInternal(array \$record, \$size) {
		if (empty(\$record['{$dataClass['id_field']}']) OR empty(\$record['{$imageField}'])) return '';

		return '/{$imagePath}/' . \$record['{$dataClass['id_field']}']  . '_' . \$record['{$imageField}'] . strtolower(\$size) . '.jpg';
	}
EOF;

		return $contents;
	}
	
	public static function generateGetDataFunctionName(array $addOn, DevHelper_Config_Base $config, array $dataClass) {
		return 'get' . (empty($dataClass['camelCasePlural']) ? ('All' . $dataClass['camelCase']) : $dataClass['camelCasePlural']);
	}
	
	public static function generateCountDataFunctionName(array $addOn, DevHelper_Config_Base $config, array $dataClass) {
		return 'count' . (empty($dataClass['camelCasePlural']) ? ('All' . $dataClass['camelCase']) : $dataClass['camelCasePlural']);
	}
}