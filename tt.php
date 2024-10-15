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