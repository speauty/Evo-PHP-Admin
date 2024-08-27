<?php

namespace app\http\generate\service;

class LogicService
{

    /**
     * 生成逻辑层
     * @param array $params
     * @return string
     */
    public static function handleLogic(array $params): string
    {
        // 需要替换的变量
        $needReplace = [
            '{NAMESPACE}',
            '{USE}',
            '{CLASS_COMMENT}',
            '{DATE}',
            '{UPPER_CAMEL_NAME}',
            '{MODULE_NAME}',
            '{PACKAGE_NAME}',
            '{FUNCTIONS}'
        ];

        // 等待替换的内容
        $waitReplace = [
            GenerateService::getNameSpaceContent($params['moduleName'], $params['classDir'], $params['upperCameName'], 'logic'),
            self::getUseContent($params['classDir'], $params['upperCameName']),
            $params['classComment'].'逻辑类',
            $params['date'],
            $params['upperCameName'],
            $params['moduleName'],
            $params['packageName'],
            self::handleFunctions($params['gen'], $params['classComment'], $params['date'], $params['upperCameName'], $params['PK'], $params['fields'])
        ];
        $templatePath = GenerateService::getTemplatePath('php/logic');
        // 替换内容
        return GenerateService::replaceFileData($needReplace, $waitReplace, $templatePath);
    }


    /**
     * 获取use内容
     * @param string $classDir
     * @param string $tableName
     * @return string
     */
    private static function getUseContent(string $classDir, string $tableName): string
    {
        if (empty($classDir)) {
            $tpl = "use app\\common\\model\\" . $classDir . "Model;";
        } else {
            $tpl = "use app\\common\\model\\" . $classDir . "\\" . $tableName . "Model;";
        }
        return $tpl;
    }


    /**
     * 处理方法
     * @param array $gen
     * @param string $notes
     * @param string $date
     * @param string $upperCameName
     * @param string $PK
     * @param array $fields
     * @return string
     */
    private static function handleFunctions(array $gen, string $notes, string $date, string $upperCameName, string $PK, array $fields): string
    {
        $action = [
            'list' => [],
            'create' => [],
            'update' => [],
            'detail' => [],
            'query' => []
        ];
        foreach ($fields as $field) {
            if ($field['LIST']) $action['list'][] = $field;
            if ($field['CREATE']) $action['create'][] = $field;
            if ($field['UPDATE']) $action['update'][] = $field;
            if ($field['DETAIL']) $action['detail'][] = $field;
            if ($field['QUERY_TYPE']) $action['query'][] = $field;
        }
        $content = '';
        if ($gen['logic']['list']) {
            $content .= self::handleLists($notes, $date, $upperCameName, $action['query'], $action['list'], $gen['paginate']) . PHP_EOL;
        }
        if ($gen['logic']['create']) {
            $content .= self::handleCreate($notes, $date, $upperCameName, $action['create']) . PHP_EOL;
        }
        if ($gen['logic']['update']) {
            $content .= self::handleUpdate($notes, $date, $upperCameName, $PK, $action['update']) . PHP_EOL;
        }
        if ($gen['logic']['detail']) {
            $content .= self::handleDetail($notes, $date, $upperCameName, $PK, $action['detail']) . PHP_EOL;
        }
        if ($gen['logic']['delete']) {
            $content .= self::handleDelete($notes, $date, $upperCameName, $PK);
        }
        return $content;
    }

    /**
     * 处理列表方法
     * @param string $notes
     * @param string $date
     * @param string $upperCameName
     * @param array $queryColumn
     * @param $listColumn
     * @param bool $paginate
     * @return string
     */
    private static function handleLists(string $notes, string $date, string $upperCameName, array $queryColumn, $listColumn, bool $paginate): string
    {
        // 需要替换的变量
        $needReplace = [
            '{NOTES}',
            '{DATE}',
            '{UPPER_CAMEL_NAME}',
            '{QUERY_CONDITION}',
            '{FILTER_DATA}',
            '{OTHER_WHERE}',
            '{FIELDS}',
            '{METHOD_LIST}',
            '{FORMAT_DATA}'
        ];

        // 处理查询条件
        $formatQuery = self::getFormatQueryContent($queryColumn);
        // 处理字段显示问题
        $fields = '*';
        if (!empty($listColumn)) {
            $fields = '';
            foreach ($listColumn as $value) {
                $fields .= "'" . $value['COLUMN_NAME'] . "'" . ',';
            }
            // 去除最后一个逗号
            $fields = substr($fields, 0, -1);
        }

        // 处理分页问题
        if ($paginate) {
            $methodList = 'paginate($params["pageSize"] ?? 10);';
            $formatData = 'return formattedPaginate($list);';
        } else {
            $methodList = 'get()';
            $formatData = 'return empty($list) ? [] : $list->toArray();';
        }
        // 等待替换的内容
        $waitReplace = [
            $notes,
            $date,
            $upperCameName,
            $formatQuery['content'] ?? '',
            $formatQuery['filterStr'] ?? '',
            $formatQuery['otherWhere'] ?? '',
            $fields,
            $methodList,
            $formatData
        ];
        $templatePath = GenerateService::getTemplatePath('php/logic/listsLogic');
        return GenerateService::replaceFileData($needReplace, $waitReplace, $templatePath);
    }

    /**
     * 处理创建方法
     * @param string $notes
     * @param string $date
     * @param string $upperCameName
     * @param array $createColumn
     * @return string
     */
    private static function handleCreate(string $notes, string $date, string $upperCameName, array $createColumn): string
    {
        // 需要替换的变量
        $needReplace = [
            '{NOTES}',
            '{DATE}',
            '{UPPER_CAMEL_NAME}',
            'CREATE_DATA'
        ];
        $updateData = self::getFormatDataContent($createColumn, 'create');
        // 等待替换的内容
        $waitReplace = [
            $notes,
            $date,
            $upperCameName,
            $updateData
        ];
        $templatePath = GenerateService::getTemplatePath('php/logic/createLogic');
        return GenerateService::replaceFileData($needReplace, $waitReplace, $templatePath);
    }

    /**
     * 处理更新方法
     * @param string $notes
     * @param string $date
     * @param string $upperCameName
     * @param string $pk
     * @param array $updateColumn
     * @return string
     */
    private static function handleUpdate(string $notes, string $date, string $upperCameName, string $pk, array $updateColumn): string
    {
        // 需要替换的变量
        $needReplace = [
            '{NOTES}',
            '{DATE}',
            '{UPPER_CAMEL_NAME}',
            '{PK}',
            '{WHERE_PK}',
            'UPDATE_DATA'
        ];
        $createData = self::getFormatDataContent($updateColumn);
        // 等待替换的内容
        $waitReplace = [
            $notes,
            $date,
            $upperCameName,
            $pk,
            GenerateService::snakeToCamelCase($pk),
            $createData
        ];
        $templatePath = GenerateService::getTemplatePath('php/logic/updateLogic');
        return GenerateService::replaceFileData($needReplace, $waitReplace, $templatePath);
    }


    /**
     * 处理删除方法
     * @param string $notes
     * @param string $date
     * @param string $upperCameName
     * @param string $pk
     * @return string
     */
    private static function handleDelete(string $notes, string $date, string $upperCameName, string $pk): string
    {
        // 需要替换的变量
        $needReplace = [
            '{NOTES}',
            '{DATE}',
            '{UPPER_CAMEL_NAME}',
            '{PK}',
            '{WHERE_PK}'
        ];
        // 等待替换的内容
        $waitReplace = [
            $notes,
            $date,
            $upperCameName,
            $pk,
            GenerateService::snakeToCamelCase($pk),
        ];
        $templatePath = GenerateService::getTemplatePath('php/logic/deleteLogic');
        return GenerateService::replaceFileData($needReplace, $waitReplace, $templatePath);
    }


    /**
     * 处理详情方法
     * @param string $notes
     * @param string $date
     * @param string $upperCameName
     * @param string $pk
     * @param array $fields
     * @return string
     */
    private static function handleDetail(string $notes, string $date, string $upperCameName, string $pk, array $fields): string
    {
        // 需要替换的变量
        $needReplace = [
            '{NOTES}',
            '{DATE}',
            '{UPPER_CAMEL_NAME}',
            '{PK}',
            '{WHERE_PK}',
            '{FIELDS}'
        ];
        $fields = '*';
        if (!empty($listColumn)) {
            $fields = '';
            foreach ($listColumn as $value) {
                $fields .= "'" . $value['COLUMN_NAME'] . "'" . ',';
            }
            // 去除最后一个逗号
            $fields = substr($fields, 0, -1);
        }
        // 等待替换的内容
        $waitReplace = [
            $notes,
            $date,
            $upperCameName,
            $pk,
            GenerateService::snakeToCamelCase($pk),
            $fields
        ];
        $templatePath = GenerateService::getTemplatePath('php/logic/detailLogic');
        return GenerateService::replaceFileData($needReplace, $waitReplace, $templatePath);
    }


    /**
     *
     * @param array $tableColumn
     * @param string $flag
     * @return string
     */
    private static function getFormatDataContent(array $tableColumn, string $flag = ''): string
    {
        if (empty($tableColumn)) {
            return '';
        }
        $content = '';
        foreach ($tableColumn as $column) {
            $content .= self::formatColumn($column);
        }
        if (empty($content)) {
            return $content;
        }
        if ($flag == 'create') {
            $content .= "'created_at' => " . 'time()' . ',' . PHP_EOL;
        }
        $content .= "'updated_at' => " . 'time()' . ',';
        $content = substr($content, 0, -1);
        return GenerateService::setBlankSpace($content, "                ");
    }

    /**
     * 格式化字段
     * @param array $column
     * @return string
     */
    private static function formatColumn(array $column): string
    {
//        if ($column['column_type'] == 'int' && $column['view_type'] == 'datetime') {
//            // 物理类型为int，显示类型选择日期的情况
//            $content = "'" . $column['column_name'] . "' => " . 'strtotime($params[' . "'" . $column['column_name'] . "'" . ']),' . PHP_EOL;
//        } else {
        //        }
        $columnName = GenerateService::snakeToCamelCase($column['COLUMN_NAME']);
        return "'" . $column['COLUMN_NAME'] . "' => " . '$params[' . "'" . $columnName . "'" . '],' . PHP_EOL;
    }


    /**
     * 获取查询条件
     * @param array $tableColumn
     * @return array
     */
    private static function getFormatQueryContent(array $tableColumn): array
    {
        if (empty($tableColumn)) {
            return [];
        }
        $content = '';

        $filterStr = '';
        $filterWhere = '';
        foreach ($tableColumn as $column) {
            $content .= "'" . $column['COLUMN_NAME'] . "' => " . 'null' . ',' . PHP_EOL;
            if (!empty($column['QUERY_TYPE'])) {
                $columnName = GenerateService::snakeToCamelCase($column['COLUMN_NAME']);
                $value = '$param[\'' . $columnName . '\']';
                if (in_array($column['QUERY_TYPE'], ['=', '!=', '>', '>=', '<', '<=', 'LIKE'])) {
                    $filter = '$filter';
                    $operators = [
                        '=' => "'=', $value",
                        '!=' => "'!=', $value",
                        '>' => "'>', $value",
                        '>=' => "'>=', $value",
                        '<' => "'<', $value",
                        '<=' => "'<=', $value",
                        'LIKE' => "'like','%'. $value. '%'"
                    ];
                    if (isset($operators[$column['QUERY_TYPE']])) {
                        $filterStr .= "!empty(" . $value . "） && " . "{$filter}[] = [" . "'{$column['COLUMN_NAME']}'" . ',' . $operators[$column['QUERY_TYPE']] . ']' . PHP_EOL;
                    }
                } elseif ($column['QUERY_TYPE'] === 'IN') {
                    $filterWhere .= "->whereIn('{$column['COLUMN_NAME']}', explode(',', $value))";
                } elseif ($column['QUERY_TYPE'] === 'BETWEEN') {
                    $filterWhere .= "->whereBetween('{$column['COLUMN_NAME']}', explode(',', $value))";
                } elseif ($column['QUERY_TYPE'] === 'NOT IN') {
                    $filterWhere .= "->whereNotIn('{$column['COLUMN_NAME']}', explode(',', $value))";
                }
            }
        }
        if (!empty($content)) {
            $content = substr($content, 0, -2);
            $content = GenerateService::setBlankSpace($content, "                ");
        }
        if (!empty($filterStr)) {
            $filterStr = GenerateService::setBlankSpace($filterStr, "            ");
        }
        return [
            'content' => $content,
            'filterStr' => $filterStr,
            'otherWhere' => $filterWhere
        ];
    }
}