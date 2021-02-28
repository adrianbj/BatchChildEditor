<?php

/**
 * ProcessWire Table CSV Export Helper
 * by Adrian Jones
 *
 * Helper process module for generating CSV from a Table field
 *
 * ProcessWire 2.x
 * Copyright (C) 2011 by Ryan Cramer
 * Licensed under GNU/GPL v2, see LICENSE.TXT
 *
 * http://www.processwire.com
 * http://www.ryancramer.com
 *
 */

class ProcessChildrenCsvExport extends Process implements Module {

    /**
     * getModuleInfo is a module required by all modules to tell ProcessWire about them
     *
     * @return array
     *
     */
    public static function getModuleInfo() {
        return array(
            'title' => __('Process Children CSV Export'),
            'version' => '1.8.24',
            'summary' => __('Helper module for BatchChildEditor for creating CSV to export'),
            'href' => 'http://modules.processwire.com/modules/batch-child-editor/',
            'singular' => true,
            'autoload' => false,
            'permission' => 'batch-child-editor',
            'requires' => 'BatchChildEditor',
            'page' => array(
                'name' => 'children-csv-export',
                'parent' => 'setup',
                'title' => 'Children CSV Export',
                'status' => 'hidden'
            )
            );
    }

    /**
     * Name used for the page created in the admin
     *
     */
    const adminPageName = 'children-csv-export';



    /**
     * Initialize the module
     *
     */
    public function init() {
        parent::init();
        $this->addHook('Page::exportCsv', $this, 'exportCsv'); /* not limited to table-csv-export permission because only relevant to front-end */
    }

    /**
     * Executed when root url for module is accessed
     *
     */
    public function ___execute() {
        $this->exportCsv();
    }


    public function outputCSV($data, $delimiter, $enclosure) {
        $output = fopen("php://output", "w");
        foreach ($data as $row) {
            fputcsv($output, $row, $delimiter == "tab" ? chr(9) : $delimiter, $enclosure);
        }
        fclose($output);
    }


    public function exportCsv($event = NULL) {

        $defaultSettings = wire('modules')->get("BatchChildEditor")->getDefaultData();
        $systemFields = $defaultSettings['systemFields'];

        $configSettings = wire('modules')->getModuleConfigData("BatchChildEditor");

        $pp = !is_null($event) ? $event->object : wire('pages')->get((int) wire('input')->get->pid);
        $delimiter = !is_null($event) ? $event->arguments(0) : wire('input')->get->cs;
        $enclosure = !is_null($event) ? $event->arguments(1) : wire('input')->get->ce;
        $extension = !is_null($event) ? $event->arguments(2) : wire('input')->get->ext;
        $namesFirstRow = !is_null($event) ? $event->arguments(3) : wire('input')->get->nfr;
        $namesFirstRow = $namesFirstRow == 'checked' || $namesFirstRow == '1' ? true : false;
        $exportMultipleValuesSeparator = !is_null($event) ? $event->arguments(4) : wire('input')->get->mvs;
        $formatExport = !is_null($event) ? $event->arguments(5) : wire('input')->get->fe;
        $formatExport = $formatExport == 'checked' || $formatExport == 1 ? true : false;
        $pagesToInclude = !is_null($event) ? $event->arguments(6) : wire('input')->get->pti;
        $fieldNames = !is_null($event) ? $event->arguments(7) : explode(',', wire('input')->get->fns);

        //if settings not supplied, use defaults from page or module config settings
        $currentData = isset($configSettings['configurablePages']) && in_array($pp->id, $configSettings['configurablePages']) && isset($configSettings['pageSettings'][$pp->id]) && $configSettings['pageSettings'][$pp->id] ? $configSettings['pageSettings'][$pp->id] : $configSettings;

        if(isset($currentData['parentPage']) && $currentData['parentPage']) $pp = wire('pages')->get($currentData['parentPage']);

        $delimiter = $delimiter ? $delimiter : $currentData['csvExportFieldSeparator'];
        $enclosure = $enclosure ? $enclosure : $currentData['csvExportFieldEnclosure'];
        $extension = $extension ? $extension : $currentData['csvExportExtension'];
        $namesFirstRow = isset($namesFirstRow) ? $namesFirstRow : $currentData['columnsFirstRow'];
        $exportMultipleValuesSeparator = $exportMultipleValuesSeparator ? $exportMultipleValuesSeparator : $currentData['exportMultipleValuesSeparator'];
        if($exportMultipleValuesSeparator == '\r') $exportMultipleValuesSeparator = chr(13);
        if($exportMultipleValuesSeparator == '\n') $exportMultipleValuesSeparator = chr(10);
        $formatExport = isset($formatExport) ? $formatExport : $currentData['formatExport'];
        if(!$pagesToInclude) {
            $pagesToInclude = isset($currentData['pagesToInclude']) ? $currentData['pagesToInclude'] : '';
        }
        $fieldNames = $fieldNames ? $fieldNames : $currentData['exportFields'];

        if($fieldNames[0] == 'undefined') {
            $fieldNames = array();
            // if fields not defined, then get list from first child
            foreach ($pp->child()->fields as $f) $fieldNames[] = $f->name;
        }

        $csv = array();
        $i=0;
        $children = $pagesToInclude != '' && $pagesToInclude != 'undefined' ? $pp->children($pagesToInclude) : $pp->children();
        foreach($children as $p) {

            $p->of($formatExport); //needed to have fields formatted in the CSV

            //Names in First Row
            if($i==0 && $namesFirstRow == true) {
                foreach($fieldNames as $fieldName) {

                    //exclude unsupported field types
                    if(wire('fields')->$fieldName && (wire('fields')->$fieldName->type == 'FieldtypeTable' ||
                        wire('fields')->$fieldName->type == 'FieldtypeRepeater' ||
                        wire('fields')->$fieldName->type == 'FieldtypePageTable' ||
                        wire('fields')->$fieldName->type == 'FieldsetOpen' ||
                        wire('fields')->$fieldName->type == 'FieldsetClose' ||
                        wire('fields')->$fieldName->type == 'FieldsetTabOpen' ||
                        wire('fields')->$fieldName->type == 'FieldsetTabClose'
                    )) continue;

                    //FieldtypeTextareas
                    if(wire('fields')->$fieldName && wire('fields')->$fieldName->type == 'FieldtypeTextareas') {
                        $field = wire('fields')->get($fieldName);
                        $subfields = $field->type->getBlankValue(new Page(), $field);
                        foreach($subfields as $subFieldName => $value) {
                            $csv[$i][] = $field->type->getLabel($field, $subFieldName) ? $field->type->getLabel($field, $subFieldName) : $subFieldName;
                        }
                    }
                    /*
                    //Repeaters
                    elseif(wire('fields')->$fieldName && wire('fields')->$fieldName->type == 'FieldtypeRepeater') {
                        $field = wire('fields')->get($fieldName);
                        $subfields = $field->repeaterFields;
                        foreach($subfields as $sf) {
                            $subField = wire('fields')->get($sf);
                            $csv[$i][] = $subField->label ? $subField->label : $subField->name;
                        }
                    }
                    */
                    //All other fieldtypes
                    elseif(isset($fieldName) && $fieldName !== 'undefined') {
                        if(array_key_exists($fieldName, $systemFields)) {
                            $fieldLabel = $systemFields[$fieldName];
                        }
                        else {
                            $label = wire('fields')->get($fieldName)->label;
                            $fieldLabel = $label ? $label : $fieldName;
                        }
                        $csv[$i][] = $fieldLabel;
                    }
                }
            }

            //All Data Rows
            $i++;
            foreach($fieldNames as $fieldName) {

                $formattedValue = '';

                //exclude unsupported field types
                if(wire('fields')->$fieldName && (wire('fields')->$fieldName->type == 'FieldtypeTable' ||
                    wire('fields')->$fieldName->type == 'FieldtypeRepeater' ||
                    wire('fields')->$fieldName->type == 'FieldtypePageTable' ||
                    wire('fields')->$fieldName->type == 'FieldsetOpen' ||
                    wire('fields')->$fieldName->type == 'FieldsetClose' ||
                    wire('fields')->$fieldName->type == 'FieldsetTabOpen' ||
                    wire('fields')->$fieldName->type == 'FieldsetTabClose'
                )) continue;

                if(!$p->$fieldName) {
                    $formattedValue = '';
                }
                //FieldtypeTextareas
                elseif(wire('fields')->$fieldName && wire('fields')->$fieldName->type == 'FieldtypeTextareas') {
                    $field = wire('fields')->get($fieldName);
                    $subfields = $field->type->getBlankValue(new Page(), $field);
                    foreach($subfields as $subFieldName => $value) {
                        $csv[$i][] = $p->$fieldName->$subFieldName;
                    }
                }
                /*
                //FieldtypeRepeaters
                elseif(wire('fields')->$fieldName && wire('fields')->$fieldName->type == 'FieldtypeRepeater') {
                    foreach($p->$fieldName as $item) {
                        foreach($item->fields as $subField) {
                            $csv[$i][] = $item->$subField;
                        }
                    }
                }
                */
                //Page fields
                elseif(wire('fields')->$fieldName && wire('fields')->$fieldName->type instanceof FieldtypePage) {
                    if(method_exists($p->$fieldName,'implode')) {
                        if($p->$fieldName->implode($exportMultipleValuesSeparator, 'title')) { // title available
                            $formattedValue = $p->$fieldName->implode($exportMultipleValuesSeparator, 'title');
                        }
                        else { // no title so use name - eg a page field selecting from the user template
                            $formattedValue = $p->$fieldName->implode($exportMultipleValuesSeparator, 'name');
                        }
                    }
                    else {
                        $formattedValue = $p->$fieldName->title ? $p->$fieldName->title : $p->$fieldName->name;
                    }
                }
                //FieldtypeMultiplier and FieldtypeFile
                elseif(wire('fields')->$fieldName && (wire('fields')->$fieldName->type == 'FieldtypeMultiplier' || wire('fields')->$fieldName->type == 'FieldtypeOptions' || wire('fields')->$fieldName->type instanceof FieldtypeFile)) {
                    if(wire('fields')->$fieldName->type instanceof FieldtypeFile) $p->of(false); //formatting off required if output format is "Rendered string of text"
                    if(count($p->$fieldName)>0) {
                        $values = array();
                        foreach($p->$fieldName as $value) {
                            if(wire('fields')->$fieldName->type instanceof FieldtypeFile) {
                                $values[] = $value->filename;
                            }
                            elseif($formatExport && wire('fields')->$fieldName->type == 'FieldtypeOptions') {
                                $values[] = $value->title;
                            }
                            else {
                                $values[] = $value;
                            }
                        }
                        $formattedValue = implode($exportMultipleValuesSeparator, $values);
                    }
                    $p->of($formatExport);
                }
                elseif(wire('fields')->$fieldName->type == "FieldtypeMapMarker") {
                    foreach(array('address', 'lat', 'lng', 'zoom', 'status') as $subFieldName) {
                        $values[] = $p->$fieldName->$subFieldName;
                    }
                    $formattedValue = implode($exportMultipleValuesSeparator, $values);
                }
                elseif(wire('fields')->$fieldName->type instanceof FieldtypeMulti && count($p->$fieldName) === 0) {
                    $formattedValue = '';
                }
                //All other fields
                else {
                    $formattedValue = $p->$fieldName;
                }

                //Populate $csv array for all fields that don't have subfields and are therefore already populated, like FieldtypeTextareas
                if(array_key_exists($fieldName, $systemFields) || (wire('fields')->$fieldName && wire('fields')->$fieldName->type != 'FieldtypeTextareas')) {
                    $csv[$i][] = $formattedValue;
                }

            }

        }

        header("Content-type: text/csv");
        header("Content-Disposition: attachment; filename=".$pp->name .".".$extension);
        header("Pragma: no-cache");
        header("Expires: 0");

        $this->outputCSV($csv, $delimiter, $enclosure);
        exit;

    }

}
