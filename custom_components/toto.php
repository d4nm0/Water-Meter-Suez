<?php
/**
 * Subject file  
 * 
 * PHP version 5
 * 
 * @category Audit
 * 
 * @package Backend
 * 
 * @author EISGE <kalifast@eisge.com>
 * 
 * @license kalifast.com Kalifast
 * 
 * @version SVN: $Id$
 * 
 * @link kalifast.com
 */ 

/**
 * Audit class 
 * 
 * @category Audit
 * 
 * @package Backend
 * 
 * @author EISGE <kalifast@eisge.com>
 * 
 * @license kalifast.com Kalifast
 * 
 * @link kalifast.com
 */
class Audit extends BaseApi
{

    /**
     * Ajout de l'information de création de l'audit
     * 
     * @return number
     */
    function createAudit()
    {
        $d = $this->checkParams(
            [ 
                'version' => 'string',
                'origin_db_md5_table' => 'string',
                'current_db_md5_table' => 'string',
                'origin_db_md5_view' => 'string',
                'current_db_md5_view' => 'string',
                'origin_permission_md5' => 'string',
                'current_permission_md5' => 'string',
                'deltaSqlTable' => 'string',
                'deltaSqlViews' => 'string'
            ]
        );

        $s = $this->PDO->prepare(
            'SELECT count(1) from ref_audit where audit_version=:version'
        );
        $s->execute([
            'version' => $d->version
        ]);
        $audit_version_exist = (int)($s->fetch()?:[0])[0];
        if ($audit_version_exist === 0) {
            $s = $this->PDO->prepare(
                'INSERT INTO `ref_audit` 
                (`audit_version`, `audit_datetime`, `origin_db_md5_table`, `current_db_md5_table`, `origin_db_md5_view`, `current_db_md5_view`, `origin_permission_md5`, `current_permission_md5`, `deltaSqlTable`, `deltaSqlViews`) 
                VALUES (:version, NOW(), :origin_db_md5_table, :current_db_md5_table, :origin_db_md5_view, :current_db_md5_view, :origin_permission_md5, :current_permission_md5, :deltaSqlTable, :deltaSqlViews);'
            );
            $res = $s->execute(
                [
                    'version' => $d->version,
                    'origin_db_md5_table' => $d->origin_db_md5_table,
                    'current_db_md5_table' => $d->current_db_md5_table,
                    'origin_db_md5_view' => $d->origin_db_md5_view,
                    'current_db_md5_view' => $d->current_db_md5_view,
                    'origin_permission_md5' => $d->origin_permission_md5,
                    'current_permission_md5' => $d->current_permission_md5,
                    'deltaSqlTable' => $d->deltaSqlTable,
                    'deltaSqlViews' => $d->deltaSqlViews
                ]
            );
        } else {
            $s = $this->PDO->prepare(
                'UPDATE `ref_audit` SET `audit_datetime`=now() WHERE `audit_version`=:version'
            );
            $res = $s->execute(
                [
                    'version' => $d->version
                ]
            );
        } 
         
        return true;
    }


    /**
     * Verifier si l'audit a deja été créé et s'il a moins de 24 h
     * 
     * @return number
     */
    function verifAuditCreation()
    {
        $d = $this->checkParams(
            [ 
                'version' => 'string'
            ]
        );

        $s = $this->PDO->prepare(
            'SELECT * from ref_audit where audit_version=:version AND audit_datetime >= NOW() - INTERVAL 1 DAY;'
        );
        $s->execute([
            'version' => $d->version
        ]);

        $Auditdata = $s->fetch(PDO::FETCH_ASSOC);

        $this->setData($Auditdata);
       
        return true;
    }

    /**
     * parseTables
     * 
     * @return string
     */
    function parseTables() {

        $d = $this->checkParams(
            [
                'htmlContent' => 'string'
            ]
        );

        preg_match_all('/Table\s+(\w+)\s*\{([^}]*)\}/is', $d->htmlContent, $matches);
        
        $tables = [];
        foreach ($matches[1] as $index => $tableName) {
            $tableDefinition = trim($matches[2][$index]);
            $lines = array_map('trim', explode("\n", $tableDefinition));
            $columns = [];
            foreach ($lines as $line) {
                $line = trim($line, ",");  // Enlever les virgules en fin de ligne
                if (!empty($line)) {
                    $parts = explode(' ', $line);
                    $columnName = $parts[0];  // Nom de la colonne
                    $columns[$columnName][] = $line;  // Stocker les définitions en tableau pour gérer les doublons
                }
            }
            $tables[$tableName] = $columns;
        }

        return $tables;
    }

    /**
     * calculateColumnSimilarity
     * 
     * @return string
     */
    function calculateColumnSimilarity() {

        $d = $this->checkParams(
            [
                'refColumn' => 'string',
                'appColumn' => 'string'
            ]
        );

        $refParts = explode(' ', $d->refColumn);
        $appParts = explode(' ', $d->appColumn);
        
        $score = 0;
        
        if ($refParts[1] == $appParts[1]) {
            $score += 50;
        }
        
        $refAttributes = array_slice($refParts, 2);
        $appAttributes = array_slice($appParts, 2);
        
        foreach ($refAttributes as $attribute) {
            if (in_array($attribute, $appAttributes)) {
                $score += 10;
            }
        }
        
        return $score;
    }

    /**
     * findMostSimilarColumn
     * 
     * @return string
     */
    function findMostSimilarColumn() {

        $d = $this->checkParams(
            [
                'refColumn' => 'string',
                'appColumn' => 'string'
            ]
        );

        $bestMatch = null;
        $highestScore = 0;

        foreach ($d->appColumns as $appColumn) {
            $similarityScore = calculateColumnSimilarity($d->refColumn, $appColumn);
            if ($similarityScore > $highestScore) {
                $highestScore = $similarityScore;
                $bestMatch = $appColumn;
            }
        }

        return $bestMatch;
    }
 

    /**
     * MainComparingAudits
     * 
     * @return string
     */
    function mainComparingAudits() {

        $d = $this->checkParams(
            [
                'referenceFile' => 'string',
                'applicationFile' => 'string',
                'project_version' => 'string',
                'type' => 'string'
            ]
        );
        $path_frontend = $_SERVER['DOCUMENT_ROOT'];
        
        $referenceContent = file_get_contents($d->referenceFile);
        $applicationContent = file_get_contents($d->applicationFile);

        $referenceTables = parseTables($referenceContent);
        $applicationTables = parseTables($applicationContent);

        list($sqlCommandsWithComments, $sqlCommandsSummary) = generateSQLForDifferences($referenceTables, $applicationTables);
        $result_str = "";

        if (empty($sqlCommandsWithComments)) {
            $result_str .= "-- Fichier identique, aucune action nécessaire." . PHP_EOL;
        } else {
            $result_str .= "-- Résumé des commandes SQL à exécuter pour synchroniser l'application:" . PHP_EOL;
            $result_str .= implode(PHP_EOL, $sqlCommandsSummary) . PHP_EOL;
        }

        file_put_contents("$path_frontend/audit/delta-".$d->project_version."_".$d->type.".sql", $result_str);
        $this->setData(
                [
                    'file' => "delta-".$d->project_version."_".$d->type.".sql"
                ]
            );
        return true;

    }

    /**
     * generateDBML
     * 
     * @return string
     */
    function generateDBML( ) {
        $d = $this->checkParams(
            [
                'project_version' => 'string'
            ]
        );
        $version = $d->project_version;
        $path_frontend = $_SERVER['DOCUMENT_ROOT'];
 
        $audit_file ="$path_frontend/audit/".$version."_current_table_dbml.html";
        $audit = fopen($audit_file, "w");
        if(!$audit) {
            error_log("Impossible d'ouvrir le fichier d'audit");
            return false;
        }
    
        $result = "";
        // Récupérer les tables
        $s = $this->PDO->prepare("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'kalifast' AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME");
        $s->execute([]);
        $stmt = $s->fetchAll(PDO::FETCH_ASSOC);
        $tables = $stmt;
        foreach ($tables as $table) { 
            $tableName = $table['TABLE_NAME'];
            $tables[] = $tableName;

            $result.= "Table $tableName {<br>";
             
            // Colonnes
           $s = $this->PDO->prepare("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'kalifast' AND TABLE_NAME = '$tableName' ORDER BY ORDINAL_POSITION");
            $s->execute([]);
            $columnsQuery = $s->fetchAll(PDO::FETCH_ASSOC);
            foreach ($columnsQuery as $column) {
                $columnDetails = [];
                $columnrow = $column['COLUMN_NAME'] . " " . $column['COLUMN_TYPE'];

                if ($column['IS_NULLABLE'] === 'NO') {
                    $columnDetails[] = "not null";
                }
                if ($column['COLUMN_KEY'] === 'PRI') {
                    $columnDetails[] = "pk";
                }
                if ($column['COLUMN_DEFAULT'] !== null) {
                    if (strpos($column['COLUMN_DEFAULT'], '(') !== false) {
                        $columnDetails[] = "default: `" . $column['COLUMN_DEFAULT'] . "`";
                    } else {
                        // Échapper les apostrophes dans la valeur par défaut
                        $defaultValue = str_replace("'", "\\'", $column['COLUMN_DEFAULT']);
                        $columnDetails[] = "default: '" . $defaultValue . "'";
                    }
                }
                if (  isset($columnDetails[0])) {
                    $result.= "&nbsp;" .$columnrow . " [".implode(',', $columnDetails) . "]<br>";
                }else {
                    $result.= "&nbsp;" .$columnrow . "<br>";
                }

            }

            // Indexes
            $s = $this->PDO->prepare("SELECT INDEX_NAME, COLUMN_NAME, NON_UNIQUE FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = 'kalifast' AND TABLE_NAME = '$tableName' ORDER BY INDEX_NAME, COLUMN_NAME");
            $s->execute([]);
            $indexesQuery = $s->fetchAll(PDO::FETCH_ASSOC);
            $indexes = [];
            foreach ($indexesQuery as $index) { 
                $indexes[$index['INDEX_NAME']][] = $index['COLUMN_NAME'];
            }
            foreach ($indexes as $indexName => $columns) {
                $result.= "  Indexes {<br>";
                $result.= "    (" . implode(', ', $columns) . ") [name: '" . $indexName . "']<br>";
                $result.= "  }<br>";
            }

            $result.= "}<br><br>";
        }
        fwrite($audit, $result);
        fclose($audit);
        
        
        $audit_txt = fopen("$path_frontend/audit/".$version."_current_table_dbml.txt", "w");
        $result = str_replace("&nbsp;", " ", $result);
        fwrite($audit_txt, str_replace("<br>", "\n", $result));
        fclose($audit_txt);

        $this->callClass(
            "Audit", 
            "generateDBMLviews",
            [
                'project_version' => $version,
            ]
        );

        # redirect to the audit file 
        return $result;
    }

    /**
     * generateDBML
     * 
     * @return string
     */
    function generateDBMLviews( ) {
        $d = $this->checkParams(
            [
                'project_version' => 'string'
            ]
        );
        $version = $d->project_version;
        $path_frontend = $_SERVER['DOCUMENT_ROOT'];
 
        $audit_file ="$path_frontend/audit/".$version."_current_views_dbml.html";
        $audit = fopen($audit_file, "w");
        if(!$audit) {
            error_log("Impossible d'ouvrir le fichier d'audit");
            return false;
        }
    
        $result = "";
        $result.= '/////// VIEWS <br>'; 
        $s = $this->PDO->prepare("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.VIEWS WHERE TABLE_SCHEMA = 'kalifast' ORDER BY TABLE_NAME");
        $s->execute([]);
        $viewsQuery = $s->fetchAll(PDO::FETCH_ASSOC);
        foreach ($viewsQuery as $view) { 
            $viewName = $view['TABLE_NAME'];
            $result.= "Table $viewName {<br>";

                
            // Colonnes 
            
            $s = $this->PDO->prepare("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'kalifast' AND TABLE_NAME = '$viewName' ORDER BY ORDINAL_POSITION");
            $s->execute([]);
            $columnsQuery = $s->fetchAll(PDO::FETCH_ASSOC);

            foreach ($columnsQuery as $column) { 
                $columnDetails = [];
                $columnrow = $column['COLUMN_NAME'] . " " . $column['COLUMN_TYPE'];

                if ($column['IS_NULLABLE'] === 'NO') {
                    $columnDetails[] = "not null";
                }
                if ($column['COLUMN_KEY'] === 'PRI') {
                    $columnDetails[] = "pk";
                }
                if ($column['COLUMN_DEFAULT'] !== null) {
                    if (strpos($column['COLUMN_DEFAULT'], '(') !== false) {
                        $columnDetails[] = "default: `" . $column['COLUMN_DEFAULT'] . "`";
                    } else {
                        // Échapper les apostrophes dans la valeur par défaut
                        $defaultValue = str_replace("'", "\\'", $column['COLUMN_DEFAULT']);
                        $columnDetails[] = "default: '" . $defaultValue . "'";
                    }
                }
                if (  isset($columnDetails[0])) {
                    $result.= "  " .$columnrow . " [".implode(',', $columnDetails) . "]<br>";
                }else {
                    $result.= "  " .$columnrow . "<br>";
                }
            }


            $result.= "}<br><br>";
        } 
        fwrite($audit, $result);
        fclose($audit);
        
        
        $audit_txt = fopen("$path_frontend/audit/".$version."_current_views_dbml.txt", "w");
        $result = str_replace("&nbsp;", " ", $result);
        fwrite($audit_txt, str_replace("<br>", "\n", $result));
        fclose($audit_txt);

        # redirect to the audit file 
        return $result;
    }

    /**
     * getDistantAudit
     * 
     * @return string
     */
    function getDistantAudit(){
        $d = $this->checkParams(
            [
                'project_version' => 'string'
            ]
        );  
        $path_frontend = $_SERVER['DOCUMENT_ROOT']; 
        if (!is_dir("$path_frontend/audit")) {
            mkdir("$path_frontend/audit", 0755, true); // 0755 est une permission typique pour les répertoires
        }
        // do a GET rquest to get all files in "https://dl.kalifast.com/audit/" 
        $url = "https://dl.kalifast.com/audit/".$d->project_version."_dl.kalifast.com_table_dbml.html";
        //GET request on $url and echo response code 
        $response = get_headers($url);
        $response_code = substr($response[0], 9, 3);
        $success_request = $response_code == 200 ? true : false;
        if($success_request){
            $file = file_get_contents($url);
            file_put_contents("$path_frontend/audit/".$d->project_version."_dl.kalifast.com_table_dbml.html", $file);
            
            $audit_compare_file = "https://dl.kalifast.com/audit/".$d->project_version."_dl.kalifast.com_table_dbml.txt";

            // download .txt file (not as html)
            $file = file_get_contents($audit_compare_file);
            file_put_contents("$path_frontend/audit/".$d->project_version."_dl.kalifast.com_table_dbml.txt", $file);
            

        } 

        $url = "https://dl.kalifast.com/audit/".$d->project_version."_dl.kalifast.com_views_dbml.html";
        //GET request on $url and echo response code 
        $response = get_headers($url);
        $response_code = substr($response[0], 9, 3);
        $success_request = $response_code == 200 ? true : false;
        if($success_request){
            $file = file_get_contents($url);
            file_put_contents("$path_frontend/audit/".$d->project_version."_dl.kalifast.com_views_dbml.html", $file);
            
            $audit_compare_file = "https://dl.kalifast.com/audit/".$d->project_version."_dl.kalifast.com_views_dbml.txt";

            // download .txt file (not as html)
            $file = file_get_contents($audit_compare_file);
            file_put_contents("$path_frontend/audit/".$d->project_version."_dl.kalifast.com_views_dbml.txt", $file);
            

        } 

        $url = "https://dl.kalifast.com/audit/".$d->project_version."_dl.kalifast.com_permission_audit.csv";
        $response = get_headers($url);
        $response_code = substr($response[0], 9, 3);
        $success_request = $response_code == 200 ? true : false;
        if($success_request){
            $file = file_get_contents($url);
            file_put_contents("$path_frontend/audit/".$d->project_version."_dl.kalifast.com_permission_audit.csv", $file);

        }

        $url = "https://dl.kalifast.com/audit/".$d->project_version."_dl.kalifast.com_create_views.sql";
        $response = get_headers($url);
        $response_code = substr($response[0], 9, 3);
        $success_request = $response_code == 200 ? true : false;
        if($success_request){
            $file = file_get_contents($url);
            file_put_contents("$path_frontend/audit/".$d->project_version."_dl.kalifast.com_create_views.sql", $file);

        }


        $files = [
            'ei_api_application.csv',
            'ei_api_application_module.csv',
            'ei_api_application_module_mode.csv',
            'ei_api_application_module_mode_library_action.csv',
            'ei_api_application_module_mode_permission.csv',
            'ei_api_library.csv',
            'ei_api_library_action.csv',
            'ei_api_permission.csv',
            'ei_api_role.csv',
            'ei_api_role_permission.csv'
        ];

        foreach ($files as $key => $value) { 
            $url = "https://dl.kalifast.com/audit/".$d->project_version."_dl.kalifast.com_".$value;
            $response = get_headers($url);
            $response_code = substr($response[0], 9, 3);
            $success_request_files = $response_code == 200 ? true : false;
            if($success_request_files){
                $file = file_get_contents($url);
                file_put_contents("$path_frontend/audit/".$d->project_version."_dl.kalifast.com_".$value, $file);

            }
        }
 
        if ($success_request && $success_request) {
            $this->setData(
                [
                    'version' => $d->project_version
                ]
            );
            return true;
        } else {
            return false;
        }
    }
    /**
     * generateSecurityTemplate
     * 
     * @return string
     */
    function generateSecurityTemplate() {
        $d = $this->checkParams(
            [
                'project_version' => 'string'
            ]
        );  
        $path_frontend = $_SERVER['DOCUMENT_ROOT'];
    try { 
        $pathRoot = "$path_frontend/audit". '/';
        // $pathRoot = ""; 
        // Configuration pour exporter les données en CSV


        // Tableau des requêtes SQL
        $queries = [
            "SELECT * FROM ei_api_application order by ei_api_application_id asc",
            "SELECT * FROM ei_api_application_module order by ei_api_application_id asc",
            "SELECT * FROM ei_api_application_module_mode order by ei_api_application_id asc",
            "SELECT * FROM ei_api_application_module_mode_library_action order by ei_api_application_id asc",
            "SELECT * FROM ei_api_application_module_mode_permission order by ei_api_application_id asc",
            "SELECT * FROM ei_api_library order by ei_api_library_id asc",
            "SELECT * FROM ei_api_library_action order by ei_api_library_id asc",
            "SELECT * FROM ei_api_permission order by ei_api_permission_id asc",
            "SELECT * FROM ei_api_role order by ei_api_role_id asc",
            "SELECT * FROM ei_api_role_permission order by ei_api_role_id asc"
        ];
        // Exécution des requêtes
        foreach ($queries as $query) {
            preg_match('/FROM\s+([^\s]+)/', $query, $matches);
            $tableName = $matches[1];
            $s = $this->PDO->prepare($query);
            $s->execute([]);
            $stmt = $s->fetchAll(PDO::FETCH_ASSOC);
            $file = fopen($pathRoot .$d->project_version.'_current_'.$tableName . '.csv', 'w');
            
            // Parcours des résultats et écriture dans le fichier CSV 
                 
                foreach ($stmt as $value) {
                    fputcsv($file, $value);
                }
            
            // Fermeture du fichier CSV
            fclose($file);
        }

        // Chemin des fichiers CSV à concaténer
        $files = [
            $pathRoot  .$d->project_version.'_current_ei_api_application.csv',
            $pathRoot  .$d->project_version.'_current_ei_api_application_module.csv',
            $pathRoot  .$d->project_version.'_current_ei_api_application_module_mode.csv',
            $pathRoot  .$d->project_version.'_current_ei_api_application_module_mode_library_action.csv',
            $pathRoot  .$d->project_version.'_current_ei_api_application_module_mode_permission.csv',
            $pathRoot  .$d->project_version.'_current_ei_api_library.csv',
            $pathRoot  .$d->project_version.'_current_ei_api_library_action.csv',
            $pathRoot  .$d->project_version.'_current_ei_api_permission.csv',
            $pathRoot  .$d->project_version.'_current_ei_api_role.csv',
            $pathRoot  .$d->project_version.'_current_ei_api_role_permission.csv'
        ];

        // Chemin du fichier de sortie
        $outputFile = $pathRoot  .$d->project_version.'_current_permission_audit.csv';

        // Ouvrir le fichier de sortie en mode écriture
        $outputHandle = fopen($outputFile, 'w');

        // Parcourir chaque fichier CSV
        foreach ($files as $file) { 
            // Ajouter le nom du fichier dans le fichier de sortie
            $filename = pathinfo($file, PATHINFO_FILENAME);
            fwrite($outputHandle, "##########################---" . $filename . " ---##########################\n");
            // Ouvrir le fichier CSV en mode lecture
            $inputHandle = fopen($file, 'r');

            // Lire chaque ligne du fichier et écrire dans le fichier de sortie
            while (!feof($inputHandle)) {
                $line = fgets($inputHandle);
                fwrite($outputHandle, $line);
            }

            // Fermer le fichier d'entrée
            fclose($inputHandle);
        }
 

        // Fermer le fichier de sortie
        fclose($outputHandle);
        
        return true;

    } catch (PDOException $e) {
        echo "Erreur : " . $e->getMessage();
        return false;

    }
    
}


    /**
     * calculPermissionMD5
     * 
     * @return string
     */
    function calculPermissionMD5(){

        $d = $this->checkParams(
            [
                'version' => 'string'
            ]
        );
        $path_frontend = $_SERVER['DOCUMENT_ROOT'];

        $files_perm = glob("$path_frontend/audit/".$d->version."_dl.kalifast.com_permission_audit.csv");

        $current_file_permission = "$path_frontend/audit/".$d->version."_current_permission_audit.csv";

        $this->setData(
            [
                'Origin_permission_md5' => hash_file('md5', $files_perm[0]),
                'Current_permission_md5' => hash_file('md5', $current_file_permission),
                'Origin_file' => $files_perm[0],
                'Current_file' => $current_file_permission,
            ]
        );

        return true;

    }


    /**
     * calculDatabasesMD5
     * 
     * @return string
     */
    function calculDatabasesTableMD5(){
        $d = $this->checkParams(
            [
                'project_version' => 'string'
            ]
        );  
        $path_frontend = $_SERVER['DOCUMENT_ROOT'];
  

        $files = glob("$path_frontend/audit/".$d->project_version."_dl.kalifast.com_table_dbml.html");
        $patch_found = count($files) != 0;
        if ($patch_found) {
            usort($files, function($a, $b) {
                return strcmp($a, $b);
            });
            $last_file = end($files);
        }

        $current_files = glob("$path_frontend/audit/".$d->project_version."_current_table_dbml.html");


        $this->setData(
            [
                'Origin_db_md5' => hash_file('md5', $last_file),
                'Current_db_md5' => hash_file('md5', $current_files[0]), 
            ]
        );

        return true;

    }

    /**
     * calculDatabasesMD5
     * 
     * @return string
     */
    function calculDatabasesViewMD5(){
        $d = $this->checkParams(
            [
                'project_version' => 'string'
            ]
        );  
        $path_frontend = $_SERVER['DOCUMENT_ROOT'];
  

        $files = glob("$path_frontend/audit/".$d->project_version."_dl.kalifast.com_views_dbml.html");
        $patch_found = count($files) != 0;
        if ($patch_found) {
            usort($files, function($a, $b) {
                return strcmp($a, $b);
            });
            $last_file = end($files);
        }

        $current_files = glob("$path_frontend/audit/".$d->project_version."_current_views_dbml.html");


        $this->setData(
            [
                'Origin_db_md5' => hash_file('md5', $last_file),
                'Current_db_md5' => hash_file('md5', $current_files[0]), 
            ]
        );

        return true;

    }

    /**
     * calculMD5FilePerms
     * 
     * @return string
     */
    function calculMd5filesPerms(){
        $d = $this->checkParams(
            [
                'project_version' => 'string'
            ]
        );  
        $path_frontend = $_SERVER['DOCUMENT_ROOT'];  
        $pathRoot = "$path_frontend/audit". '/';
        $files = [
            'ei_api_application.csv',
            'ei_api_application_module.csv',
            'ei_api_application_module_mode.csv',
            'ei_api_application_module_mode_library_action.csv',
            'ei_api_application_module_mode_permission.csv',
            'ei_api_library.csv',
            'ei_api_library_action.csv',
            'ei_api_permission.csv',
            'ei_api_role.csv',
            'ei_api_role_permission.csv'
        ];
        $results = [];
        foreach ($files as $key => $value) {
            
            $files = glob("$path_frontend/audit/".$d->project_version."_current_".$value);
            $patch_found = count($files) != 0;
            if ($patch_found) {
                usort($files, function($a, $b) {
                    return strcmp($a, $b);
                });
                $last_file = end($files);
            }

            $current_files = glob("$path_frontend/audit/".$d->project_version."_dl.kalifast.com_".$value);
            $results[$value] = [
                'md5_current' => hash_file('md5', $last_file),
                'md5_distant' => hash_file('md5', $current_files[0])
            ];
        } 

    
        $this->setData(
            [
                'resultFileMD5' => $results
            ]
        );

        // return true;

    }

    /**
     * execute Sql for create views
     * 
     * @return string
     */
    function executeCreateViewsSQL(){

        $d = $this->checkParams(
            [
                'version' => 'string'
            ]
        );
        $path_frontend = $_SERVER['DOCUMENT_ROOT'];
  
        $query = file_get_contents("$path_frontend/audit/".$d->version."_dl.kalifast.com_create_views.sql");
        $s = $this->PDO->prepare($query); 
 
        try {
            $s->execute();
            return true;
        } catch (Exception $e) {
             return false;
        }

       

    }

}
function parseTables($htmlContent) {
    preg_match_all('/Table\s+(\w+)\s*\{([^}]*)\}/is', $htmlContent, $matches);
    
    $tables = [];
    foreach ($matches[1] as $index => $tableName) {
        $tableDefinition = trim($matches[2][$index]);
        $lines = array_map('trim', explode("\n", $tableDefinition));
        $columns = [];
        $isIndexSection = false; // Flag pour détecter les sections d'index

        foreach ($lines as $line) {
            $line = trim($line, ",");  // Enlever les virgules en fin de ligne
            if (stripos($line, 'Indexes') === 0) {
                $isIndexSection = true; // Début de la section des index
                continue; // Passer à la ligne suivante
            }
            if ($isIndexSection) {
                if (stripos($line, ')') !== false) {
                    $isIndexSection = false; // Fin de la section des index
                }
                continue; // Ignorer les lignes dans la section d'index
            }
            if (!empty($line)) {
                $parts = explode(' ', $line);
                $columnName = $parts[0];  // Nom de la colonne
                $columns[$columnName][] = $line;  // Stocker les définitions en tableau pour gérer les doublons
            }
        }
        $tables[$tableName] = $columns;
    }
    return $tables;
}

function formatColumnDefinition($definition) {
    // Supprimer les crochets et convertir la définition en syntaxe SQL
    $definition = preg_replace('/\[(.*?)\]/', '', $definition);
    $definition = trim($definition);
    return $definition;
}

function generateSQLForDifferences($referenceTables, $applicationTables) {
    $sqlCommandsWithComments = [];
    $sqlCommandsSummary = [];

    foreach ($referenceTables as $tableName => $refColumns) {
        if (!isset($applicationTables[$tableName])) {
            // Table entière manquante
            $sqlCommandsWithComments[] = "/* Missing entire table: $tableName */" . PHP_EOL .
                                         "CREATE TABLE `$tableName` (" . PHP_EOL .
                                         implode(",\n", array_map('formatColumnDefinition', array_merge(...array_values($refColumns)))) . PHP_EOL .
                                         ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

            $sqlCommandsSummary[] = "CREATE TABLE `$tableName` (" . PHP_EOL .
                                     implode(",\n", array_map('formatColumnDefinition', array_merge(...array_values($refColumns)))) . PHP_EOL .
                                     ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
        } else {
            // Comparer les colonnes
            $appColumns = $applicationTables[$tableName];

            foreach ($refColumns as $columnName => $refColumnDefinitions) {
                $formattedRefColumn = formatColumnDefinition($refColumnDefinitions[0]);

                if (!isset($appColumns[$columnName])) {
                    // Ajouter la colonne
                    $sqlCommandsWithComments[] = "/* Missing column in $tableName: $columnName */" . PHP_EOL .
                                                 "ALTER TABLE `$tableName` ADD $formattedRefColumn;";
                    $sqlCommandsSummary[] = "ALTER TABLE `$tableName` ADD $formattedRefColumn;";
                } elseif ($appColumns[$columnName][0] !== $refColumnDefinitions[0]) {
                    // Modifier la colonne
                    $sqlCommandsWithComments[] = "/* Column mismatch in $tableName: $columnName */" . PHP_EOL .
                                                 "ALTER TABLE `$tableName` MODIFY $formattedRefColumn;";
                    $sqlCommandsSummary[] = "ALTER TABLE `$tableName` MODIFY $formattedRefColumn;";
                }
            }

            // Vérifier les colonnes supplémentaires
            foreach ($appColumns as $appColumnName => $appColumnDefinitions) {
                if (!isset($refColumns[$appColumnName])) {
                    $sqlCommandsWithComments[] = "/* Extra column in $tableName: $appColumnName */" . PHP_EOL .
                                                 "ALTER TABLE `$tableName` DROP COLUMN IF EXISTS `$appColumnName`;";
                    $sqlCommandsSummary[] = "ALTER TABLE `$tableName` DROP COLUMN IF EXISTS `$appColumnName`;";
                }
            }
        }
    }
                
    // Vérifier les tables supplémentaires dans le fichier 2
    foreach ($applicationTables as $tableName => $appColumns) {
        if (!isset($referenceTables[$tableName])) {
            $sqlCommandsWithComments[] = "/* Extra table in application: $tableName */" . PHP_EOL .
                                         "DROP TABLE IF EXISTS `$tableName`;";
            $sqlCommandsSummary[] = "DROP TABLE IF EXISTS `$tableName`;";
        }
    }

    return [$sqlCommandsWithComments, $sqlCommandsSummary];
}

function findMostSimilarColumn($refColumn, $appColumns) {
    $bestMatch = null;
    $highestScore = 0;

    foreach ($appColumns as $appColumn) {
        $similarityScore = calculateColumnSimilarity($refColumn, $appColumn);
        if ($similarityScore > $highestScore) {
            $highestScore = $similarityScore;
            $bestMatch = $appColumn;
        }
    }

    return $bestMatch;
}

function calculateColumnSimilarity($refColumn, $appColumn) {
    $refParts = explode(' ', $refColumn);
    $appParts = explode(' ', $appColumn);
    
    $score = 0;
    
    if ($refParts[1] == $appParts[1]) {
        $score += 50;
    }
    
    $refAttributes = array_slice($refParts, 2);
    $appAttributes = array_slice($appParts, 2);
    
    foreach ($refAttributes as $attribute) {
        if (in_array($attribute, $appAttributes)) {
            $score += 10;
        }
    }
    
    return $score;
}