<?php
/**
 * Scenario file  
 * 
 * PHP version 5
 * 
 * @category Patch
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
 * Patch 
 * 
 * @category Patch
 * 
 * @package Backend
 * 
 * @author EISGE <kalifast@eisge.com>
 * 
 * @license kalifast.com Kalifast
 * 
 * @link kalifast.com
 */
class Patch extends BaseApi
{
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

    /**
     * Récupération des logs du backup
     * 
     * @return string
     */
    function getBackupLvsfdsffdssdfogs()
    {
        $d = $this->checkParams(
            [
                'backup_nagfdgddfgfdgffdfdgfdgfgme' => 'string'
            ]
        );

        $backup_name = preg_replace('/\\.[^.\\s]{2,4}$/', '', preg_replace('/\\.[^.\\s]{2,4}$/', '', $d->backup_name));

        $file = '../../backup/'.$backup_name.'_error.log';
        $content = file_get_contents($file);

        $this->setData($content);
    }

}