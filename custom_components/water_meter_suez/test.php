/**
     * Récupération des logs du backup
     * 
     * @return string
     */
    function getBackupLogs()
    {
        $d = $this->checkParams(
            [
                'backup_name' => 'string'
            ]
        );

        $backup_name = preg_replace('/\\.[^.\\s]{2,4}$/', '', preg_replace('/\\.[^.\\s]{2,4}$/', '', $d->backup_name));

        $file = '../../backup/'.$backup_name.'_error.log';
        $content = file_get_contents($file);

        $this->setData($content);
    }