<?php

/**
 * ProcessWire batch creation (titles only or CSV import for other fields), editing, sorting, deletion, and CSV export of all children under a given page.
 * by Adrian Jones
 *
 * Copyright (C) 2020 by Adrian Jones
 * Licensed under GNU/GPL v2, see LICENSE.TXT
 *
 */

class BatchChildEditor extends WireData implements Module, ConfigurableModule {

    public static function getModuleInfo() {
        return array(
            'title' => 'Batch child editor',
            'summary' => 'Quick batch creation (titles only or CSV import for other fields), editing, sorting, deletion, and CSV export of all children under a given page.',
            'author' => 'Adrian Jones',
            'href' => 'http://modules.processwire.com/modules/batch-child-editor/',
            'version' => '1.8.24',
            'autoload' => "template=admin",
            'requires' => 'ProcessWire>=2.5.24',
            'installs' => 'ProcessChildrenCsvExport',
            'icon'     => 'child',
            'permissions' => array(
                'batch-child-editor' => 'Batch Child Editor'
            )
        );
    }


    /**
     * Data as used by the get/set functions
     *
     */
    protected $data = array();
    protected $currentData;
    protected $childPages = '';
    protected $predefinedTemplates = array();
    protected $predefinedParents = array();
    protected $allowedTemplatesEdit = null;
    protected $allowedTemplatesAdd = null;
    protected $titleMultiLanguage;

   /**
     * Default configuration for module
     *
     */
    static public function getDefaultData() {
            return array(
                "systemFields" => array(
                    'id' => __('ID'),
                    'name' => __('Name (from URL)'),
                    'path' => __('Path'),
                    'url' => __('URL'),
                    'status' => __('Status'),
                    'created' => __('Date Created'),
                    'modified' => __('Date Last Modified'),
                    'createdUser.id' => __('Created by User: ID'),
                    'createdUser.name' => __('Created by User: Name'),
                    'modifiedUser.id' => __('Modified by User: ID'),
                    'modifiedUser.name' => __('Modified by User: Name'),
                    'parent_id' => __('Parent Page ID'),
                    'parent.name' => __('Parent Page Name'),
                    'template.id' => __('Template ID'),
                    'template' => __('Template Name')
                ),
                "enabledTemplates" => array(),
                "enabledPages" => array(),
                "parentPage" => array(),
                "configurablePages" => array(),
                "editModes" => array("edit"),
                "defaultMode" => "",
                "overwriteNames" => "",
                "allowOverrideOverwriteNames" => "",
                "listerDefaultSort" => "sort",
                "listerColumns" => array('title', 'path', 'modified', 'modified_users_id'),
                "defaultFilter" => "",
                "listerConfigurable" => 1,
                "removeChildrenTab" => "",
                "hideChildren" => "",
                "loadOpen" => "",
                "openMethod" => "normal",
                "csvOptions" => array(),
                "csvOptionsCollapsed" => array(),
                "csvImportFieldSeparator" => ",",
                "csvImportFieldEnclosure" => '"',
                "ignoreFirstRow" => "",
                "pagesToInclude" => "",
                "exportFields" => "",
                "csvExportFieldSeparator" => ",",
                "csvExportFieldEnclosure" => '"',
                "csvExportExtension" => 'csv',
                "columnsFirstRow" => "",
                "importMultipleValuesSeparator" => "|",
                "exportMultipleValuesSeparator" => "|",
                "formatExport" => 1,
                "newImageFirst" => "",
                "allowOverrideCsvImportSettings" => "",
                "allowOverrideCsvExportSettings" => "",
                "fieldPairings" => "",
                "matchByFirstField" => "",
                "noCaseUpdate" => "",
                "addNewNoMatch" => "",
                "trashOrDelete" => "trash",
                "position" => "bottom",
                "insertAfterField" => array(),
                "tabName" => "Batch Child Editor",
                "disableContentProtection" => "",
                "allowTemplateChanges" => "",
                "allowAdminPages" => "",
                "editModeTitle" => "Edit Child Pages",
                "editModeDescription" => "You can edit the page titles, sort, delete, add new, or edit pages in a modal popup.",
                "editModeNotes" => "",
                "listerModeTitle" => "List Child Pages",
                "listerModeDescription" => "View child pages in a Lister interface.",
                "listerModeNotes" => "",
                "addModeTitle" => "Add Child Pages",
                "addModeDescription" => "Editing this field will add all the child page titles listed here to the existing set of child pages.",
                "addModeNotes" => "Each row is a separate page.\n\nYou can also use CSV formatted lines for populating all text/numeric fields on the page, eg:\n\"Bolivia, Plurinational State of\",BO,\"BOLIVIA, PLURINATIONAL STATE OF\",BOL,68",
                "updateModeTitle" => "Update Child Pages",
                "updateModeDescription" => "Editing this field will update the field values of the pages represented here.",
                "updateModeNotes" => "WARNING: If you use this option, the content of all fields in existing pages will be replaced.\n\nEach row is a separate page.\n\nYou can also use CSV formatted lines for populating all text/numeric fields on the page, eg:\n\"Bolivia, Plurinational State of\",BO,\"BOLIVIA, PLURINATIONAL STATE OF\",BOL,68",
                "replaceModeTitle" => "Replace Child Pages",
                "replaceModeDescription" => "Editing this field will replace all the child page titles represented here.",
                "replaceModeNotes" => "WARNING: If you use this option, all the existing child pages (and grandchildren) will be deleted and new ones created.\n\nEach row is a separate page.\n\nYou can also use CSV formatted lines for populating all text/numeric fields on the page, eg:\n\"Bolivia, Plurinational State of\",BO,\"BOLIVIA, PLURINATIONAL STATE OF\",BOL,68",
                "exportModeTitle" => "Export Child Pages",
                "exportModeDescription" => "Creates a CSV file with one row for each child page.",
                "exportModeNotes" => "",
                "pageSettings" => array(),
                "languageToPopulate" => "",
                "populateToAllLanguages" => ""
            );
    }

    /**
     * Populate the default config data
     *
     */
    public function __construct() {
        foreach(self::getDefaultData() as $key => $value) {
            $this->$key = $value;
        }
        $this->titleMultiLanguage = $this->wire('modules')->isInstalled("FieldtypePageTitleLanguage") && $this->wire('fields')->get('title')->type instanceof FieldtypePageTitleLanguage;
    }


    public function init() {
        $this->addHookAfter("ProcessPageEdit::buildFormChildren", $this, "addScript");
    }

    public function addScript($event) {
        $conf = $this->getModuleInfo();
        $version = (int) $conf['version'];
        $this->wire('config')->scripts->add($this->wire('config')->urls->BatchChildEditor . "BatchChildEditor.js?v={$version}");
        $this->wire('config')->styles->add($this->wire('config')->urls->BatchChildEditor . "BatchChildEditor.css?v={$version}");
    }

    public function ready() {

        if(!$this->wire('user')->hasPermission('batch-child-editor')) return;

        //this check ensures it the hook is only added for the main page list, not the list in the children tab
        if($this->wire('input')->get->id == 1 && $this->wire('input')->get->render=='JSON') {
            $this->addHookAfter('Page::listable', $this, 'pageListCountPages');
        }


        // we're interested in page editor only
        if($this->wire('page')->process != 'ProcessPageEdit') return;

        $id = (int)$this->wire('input')->get->id;
        if(!$id) return;

        //populate currentData with pageSettings version if page has specific settings
        $this->currentData = in_array($id, $this->data['configurablePages']) && isset($this->data['pageSettings'][$id]) && $this->data['pageSettings'][$id] ? $this->data['pageSettings'][$id] : $this->data;

        // GET parameter id tells the page that's being edited
        $this->editedPage = $this->wire('pages')->get($id);

        // if page doesn't exist or template of page doesn't allow children, then exit now
        if(!$this->editedPage->id || $this->editedPage->template->noChildren ===1) return;

        // don't even consider system templates
        if(!$this->data['allowAdminPages'] && ($this->editedPage->template->flags & Template::flagSystem)) return;

        //if checked, remove the main Children tab
        if($this->currentData['removeChildrenTab']) $this->addHookAfter('ProcessPageEdit::buildForm', $this, "removeChildrenTab");

        // if any templates or pages have been selected, then only hook if the template of the edited page has been chosen or the page has been chosen
        //if(count($this->data['enabledTemplates']) === 0 && count($this->data['enabledPages']) === 0) return;
        if(count($this->data['enabledTemplates']) !== 0 && !in_array($this->editedPage->template->name, $this->data['enabledTemplates'])) return;
        if(count($this->data['enabledPages']) !== 0 && !in_array($this->editedPage->id, $this->data['enabledPages'])) return;

        // page specific config settings
        if($this->wire('user')->isSuperuser() && count($this->data['configurablePages']) !== 0 && in_array($this->editedPage->id, $this->data['configurablePages'])) {
            $this->addHookAfter('ProcessPageEdit::buildFormSettings', $this, 'buildPageConfigForm');
            $this->addHookAfter('ProcessPageEdit::processInput', $this, 'processPageConfigForm');
        }

        //Make sure at least one edit mode has been selected in the config
        if(count($this->currentData['editModes']) == 0) return;

        if($this->currentData['position'] == 'inlineFieldset') {
            $this->addHookAfter('ProcessPageEdit::buildFormContent', $this, 'addInlineFieldset');
        }
        //if position is "new tab" we also need to check that the template family settings allow the page to have children
        elseif($this->currentData['position'] == 'newTab' && !$this->editedPage->template->noChildren) {
            $this->addHookAfter('ProcessPageEdit::buildFormContent', $this, 'addNewTab');
        }
        else {
            $this->addHookAfter('ProcessPageEdit::buildFormChildren', $this, 'addChildEditFieldset');
        }
        $this->addHookAfter('ProcessPageEdit::processInput', $this, 'saveChildren');

    }


    public function pageListCountPages(HookEvent $event) {

        foreach($this->data['pageSettings'] as $pid => $pageSettings) {
            if(isset($pageSettings['hideChildren']) && $pageSettings['hideChildren']) $this->wire('pages')->get($pid)->set('numChildren', 0);
        }

    }


    public function buildPageConfigForm(HookEvent $event) {

        $pp = $event->object->getPage();

        $inputfields = $event->return;

        $fieldset = $this->wire('modules')->get("InputfieldFieldset");
        $fieldset->attr('id', 'batch_child_editor_config_fieldset');
        $fieldset->label = __("Batch Child Editor Settings");
        if(!isset($this->data['pageSettings'][$pp->id])) $fieldset->collapsed = Inputfield::collapsedYes;

        $f = $this->wire('modules')->get("InputfieldMarkup");
        $f->attr('name', 'config_intro');
        $f->label = "";
        $f->value = "<p>These settings will override those in the main config settings for this page.</p>";
        $fieldset->append($f);

        $this->buildCoreSettings($this->data, $fieldset, $pp->id);

        $inputfields->append($fieldset);


        // check integrity of field pairings list
        if(isset($this->data['pageSettings'][$pp->id]) && $this->data['pageSettings'][$pp->id]) {
            if($this->data['pageSettings'][$pp->id]['fieldPairings'] != '') {
                $convertedFieldPairings = $this->convertFieldPairings($this->data['pageSettings'][$pp->id]['fieldPairings']);
                // if field pairings are set, make sure a title field is defined
                // maybe we want to make this optional at some point in case template doesn't actually have/require a title field
                if(!in_array('title', $convertedFieldPairings)) {
                    if(in_array('add', $this->data['pageSettings'][$pp->id]['editModes']) || in_array('replace', $this->data['pageSettings'][$pp->id]['editModes'])) {
                        $this->error($this->_("You must include a \"title\" field in the CSV Field Pairings, unless the only available modes are \"Edit\" and \"Update\"."));
                    }
                }

                // make sure all the fields exist in the templates that are possible for child pages
                $possibleTemplates = array();
                if(count($pp->template->childTemplates)==0) {
                    foreach($this->wire('templates') as $t) {
                        if(!($t->flags & Template::flagSystem)) $possibleTemplates[] = $t->name;
                    }
                }
                else {
                    foreach($pp->template->childTemplates as $t) {
                        $possibleTemplates[] = $this->wire('templates')->get($t)->name;
                    }
                }
                $missingFields = array();
                $availableFields = array();
                foreach($possibleTemplates as $t) {
                    foreach($convertedFieldPairings as $f) {
                        //check for subfields with period (.) separator and extract parent field name
                        if (strpos($f,'.')) {
                            $fieldParts = explode('.', $f);
                            $f = $fieldParts[0];
                        }
                        if($this->wire('templates')->get($t)->hasField($f)) {
                            if(!in_array($f, $availableFields)) $availableFields[] = $f;
                        }
                        else {
                            if(!in_array($f, $missingFields)) $missingFields[] = $f;
                        }
                    }
                }
                foreach($availableFields as $af) {
                    if(($key = array_search($af, $missingFields)) !== false) {
                        unset($missingFields[$key]);
                    }
                }
                if(!empty($missingFields)) $this->wire()->error($this->_("The following fields defined in \"Field Pairings\" are not available in any of the allowed templates for child pages: " . implode(', ', $missingFields)));
            }
        }
    }



    public function processPageConfigForm(HookEvent $event) {

        // ProcessPageEdit's processInput function may go recursive, so we want to skip
        // the instances where it does that by checking the second argument named "level"
        $level = $event->arguments(1);
        if($level > 0) return;

        $p = $event->object->getPage();

        $options = array("pid" => $p->id);
        foreach(self::getDefaultData() as $key => $value) {
            $options[$key] = $this->wire('input')->post->$key;
        }

        if(!isset($this->data['pageSettings'][$p->id]) || $this->data['pageSettings'][$p->id] != $options) {
            $this->saveSettings($options);
        }
    }


    public function saveSettings($options) {
        $pid = $options['pid'];
        unset($this->data['pageSettings'][$pid]); // remove existing record for this page - need a clear slate for adding new settings or if it was just disabled
        foreach($options as $key => $value) {
            $this->data['pageSettings'][$pid][$key] = $value;
        }

        // save to config data with the rest of the settings
        $this->wire('modules')->saveModuleConfigData($this->wire('modules')->get("BatchChildEditor"), $this->data);
    }


    public function addInlineFieldset(HookEvent $event) {

        $pp = $event->object->getPage();
        $form = $event->return;

        //populate currentData with pageSettings version if page has specific settings
        $this->currentData = in_array($pp->id, $this->data['configurablePages']) && isset($this->data['pageSettings'][$pp->id]) && $this->data['pageSettings'][$pp->id] ? $this->data['pageSettings'][$pp->id] : $this->data;

        if(isset($this->currentData['parentPage']) && $this->currentData['parentPage']) $pp = $this->wire('pages')->get($this->currentData['parentPage']);

        $childEditSet = $this->createChildEditSet($pp, $event->object->getPage(), $form);
        if($this->currentData['insertAfterField']) $fn = (string) $this->wire('fields')->get($this->currentData['insertAfterField'])->name;
        if(isset($fn) && $form->get($fn)) {
            $form->insertAfter($childEditSet,  $form->get($fn));
        }
        else {
            $form->append($childEditSet);
        }
    }


    public function addChildEditFieldset(HookEvent $event) {

        $pp = $event->object->getPage();
        $form = $event->return;

        //populate currentData with pageSettings version if page has specific settings
        $this->currentData = in_array($pp->id, $this->data['configurablePages']) && isset($this->data['pageSettings'][$pp->id]) && $this->data['pageSettings'][$pp->id] ? $this->data['pageSettings'][$pp->id] : $this->data;

        if(isset($this->currentData['parentPage']) && $this->currentData['parentPage']) $pp = $this->wire('pages')->get($this->currentData['parentPage']);

        $childEditSet = $this->createChildEditSet($pp, $event->object->getPage(), $form);

        if($this->currentData['position'] == 'top') $form->prepend($childEditSet);
        elseif ($this->currentData['position'] == 'replace') {

            if($this->wire('languages')) {
                $childrenLabel = $pp->template->getTabLabel('children', $this->wire('user')->language);
            }
            else {
                $childrenLabel = $pp->template->tabChildren;
            }
            $childrenLabel = $childrenLabel ?: 'Children / Subpages';

            foreach($form as $field) {
                if($field->label == $childrenLabel) $form->remove($field);
            }
            $form->prepend($childEditSet);
        }
        else $form->append($childEditSet); // bottom
    }


    public function addNewTab(HookEvent $event) {

        $pp = $event->object->getPage();
        $form = $event->return;

        //populate currentData with pageSettings version if page has specific settings
        $this->currentData = in_array($pp->id, $this->data['configurablePages']) && isset($this->data['pageSettings'][$pp->id]) && $this->data['pageSettings'][$pp->id] ? $this->data['pageSettings'][$pp->id] : $this->data;

        if(isset($this->currentData['parentPage']) && $this->currentData['parentPage']) $pp = $this->wire('pages')->get($this->currentData['parentPage']);

        //build the content of the tab
        $childEditSet = $this->createChildEditSet($pp, $event->object->getPage(), $form);

        //old approach - works, but prevents being able to position the tab anywhere but after the View tab
        //if going back to this approach, then need to change the hook that calls this method to ProcessPageEdit::buildForm
        /*
        // create the tab
        $newTab = new InputfieldWrapper();
        $newTab->attr('id', $this->className() . $this->wire('sanitizer')->pageName($this->currentData['tabName'],true));
        $newTab->attr('title', $this->currentData['tabName']);
        $newTab->prepend($childEditSet);
        $form->prepend($newTab); // this prepend is putting the childeditset before the save button, although I really want to change the order of the tab itself to just after Content
        */

        $this->wire('modules')->get('FieldtypeFieldsetTabOpen');
        if(class_exists("\ProcessWire\InputfieldFieldsetTabOpen")) {
            $field = new \ProcessWire\InputfieldFieldsetTabOpen;
        }
        else {
            $field = new InputfieldFieldsetTabOpen;
        }
        $field->name = $this->wire('sanitizer')->fieldName($this->currentData['tabName']);
        if(!$this->wire('input')->get->s) $field->collapsed = $this->currentData['openMethod'] == 'ajax' ? Inputfield::collapsedYesAjax : Inputfield::collapsedNo;
        $field->label = $this->currentData['tabName'];
        $form->add($field);

        $form->add($childEditSet);

        $this->wire('modules')->get('FieldtypeFieldsetClose');
        if(class_exists("\ProcessWire\InputfieldFieldsetClose")) {
            $field = new \ProcessWire\InputfieldFieldsetClose;
        }
        else {
            $field = new InputfieldFieldsetClose;
        }
        $field->name = $this->wire('sanitizer')->fieldName($this->currentData['tabName'])."_END";
        $form->add($field);
    }


    public function createChildEditSet($pp, $settingsPage, $form) {

        //populate currentData with pageSettings version if page has specific settings
        $this->currentData = in_array($settingsPage->id, $this->data['configurablePages']) && isset($this->data['pageSettings'][$settingsPage->id]) && $this->data['pageSettings'][$settingsPage->id] ? $this->data['pageSettings'][$settingsPage->id] : $this->data;

        if(isset($this->currentData['parentPage']) && $this->currentData['parentPage']) $pp = $this->wire('pages')->get($this->currentData['parentPage']);

        //get user CSV setting overrides if allowed
        if(isset($this->currentData['allowOverrideCsvImportSettings']) && $this->currentData['allowOverrideCsvImportSettings']) {

            $fieldSeparator = $this->currentData['csvImportFieldSeparator'];
            $fieldEnclosure = $this->currentData['csvImportFieldEnclosure'];
            $ignoreFirstRow = $this->currentData['ignoreFirstRow'];

            if($this->wire('session')->userCsvImportFieldSeparator) $this->currentData['csvImportFieldSeparator'] = $this->wire('session')->userCsvImportFieldSeparator;
            if($this->wire('session')->userCsvImportFieldEnclosure) $this->currentData['csvImportFieldEnclosure'] = $this->wire('session')->userCsvImportFieldEnclosure;
            if($this->wire('session')->userIgnoreFirstRow) $this->currentData['ignoreFirstRow'] = $this->wire('session')->userIgnoreFirstRow;
        }

        foreach($pp->children("include=all") as $cp) {
            if(!$cp->is(Page::statusSystemID)) $this->childPages .= "{$cp->title}\r\n";
        }
        $this->childPages = rtrim($this->childPages, "\r\n");
        if($this->currentData['ignoreFirstRow']) $this->childPages = "Title\r\n".$this->childPages;

        // create the fieldset
        $childEditSet = $this->wire('modules')->get("InputfieldFieldset");
        $childEditSet->attr('name', 'child_batch_editor');
        if($this->currentData['position'] != 'newTab' && !$this->wire('input')->get->s) $childEditSet->collapsed = $this->currentData['loadOpen'] ? '' : ((defined('Inputfield::collapsedYesAjax') || defined('\ProcessWire\Inputfield::collapsedYesAjax')) && $this->currentData['openMethod'] == 'ajax' ? Inputfield::collapsedYesAjax : Inputfield::collapsedYes);
        $childEditSet->label = $this->currentData['position'] == 'newTab' ? ' ' : $this->currentData['tabName'];

        if($this->wire('languages') && !$this->wire('user')->language->isDefaultLanguage) {
            $f = $this->wire('modules')->get("InputfieldMarkup");
            $f->value = '<p><strong><i class="fa fa-language"></i> ' . $this->wire('user')->language->title . '</strong></p>';
            $childEditSet->add($f);
        }

        if(count($this->currentData['editModes'])>1) {
            $editModes = array(
                'lister' => __('Lister'),
                'edit' => __('Edit'),
                'add' => __('Add'),
                'update' => __('Update'),
                'replace' => __('Replace'),
                'export' => __('Export')
            );
            $f = $this->wire('modules')->get("InputfieldRadios");
            $f->attr('name', 'edit_mode');
            $f->label = __('Mode');
            if(!$this->currentData['allowOverrideOverwriteNames'] && (is_array($pp->template->childTemplates) && count($pp->template->childTemplates)==1)) $f->optionColumns = 1;
            $f->columnWidth = 33;
            foreach($this->currentData['editModes'] as $editMode) {
                if($editMode == 'update' && $pp->children("include=all")->count == 0) continue;
                //check users add rights for add and replace mode
                if(($editMode == 'add' || $editMode == 'replace') && !$pp->addable()) continue;
                if($editMode == 'lister' && !$this->wire('user')->hasPermission('page-lister')) continue;
                $f->addOption($editMode, $editModes[$editMode]);
            }
            if($this->wire('input')->get->s && $this->wire('session')->edit_mode) {
                $f->value = $this->wire('session')->edit_mode;
            }
            else {
                if($this->currentData['defaultMode']) $f->value = $this->currentData['defaultMode'];
            }
            $childEditSet->add($f);
        }
        else {
            $f = $this->wire('modules')->get("InputfieldHidden");
            $f->attr('name', 'edit_mode');
            $f->value = $this->currentData['editModes'][0];
            $childEditSet->add($f);
        }

        if(is_array($pp->template->childTemplates) && count($pp->template->childTemplates)!=1) {
            $f = $this->wire('modules')->get("InputfieldSelect");
            $f->required = true;
            $f->name = "childTemplate";
            $f->showIf = "edit_mode.count>0, edit_mode!=export, edit_mode!=edit, edit_mode!=lister";
            $f->label = __('Child Template');
            $f->columnWidth = 34;
            $f->description = __('Choose the template for children');
            $f->notes = __('This will not change the template for existing pages (only for new children) in Update mode unless the "Update template for existing children" option is checked.');
            /*if(count($pp->template->childTemplates)==0) {
                foreach($this->wire('templates') as $t) {
                    if(!($t->flags & Template::flagSystem)) $f->addOption($t->name);
                }
            }
            else {
                $f->addOption('');
                foreach($pp->template->childTemplates as $t) {
                    $f->addOption($this->wire('templates')->get($t)->name);
                }
            }*/
            foreach($this->wire('templates') as $t) {
                if(!$t->id || !$this->isAllowedTemplateAdd($t, $pp)) continue;
                $f->addOption($this->wire('templates')->get($t)->name);
            }
            if($pp->children("include=all")->count()>0 && $pp->children("include=all")->last()->id) $f->attr('value', $pp->children("include=all")->last()->template);
            $childEditSet->add($f);

            $f = $this->wire('modules')->get("InputfieldCheckbox");
            $f->name = "updateTemplateExistingChildren";
            $f->description = __('This will change the template for existing children to match the one selected.');
            $f->notes = __('This could destroy lots of content - use with caution!');
            $f->showIf = "edit_mode.count>0, edit_mode!=edit, edit_mode=update, edit_mode!=lister";
            $f->label = __('Update template for existing children');
            $f->columnWidth = 33;
            $f->attr('checked', $this->wire('session')->updateTemplateExistingChildren === 1 ? 'checked' : '');
            $childEditSet->add($f);

        }

        if($this->currentData['allowOverrideOverwriteNames']) {
            $f = $this->wire('modules')->get("InputfieldCheckbox");
            $f->attr('name', 'userOverwriteNames');
            $f->showIf = "edit_mode=edit|update";
            $f->label = __('Overwrite names');
            $f->columnWidth = 33;
            $f->description = __('Whether to overwrite the name of the page, and not just the title.');
            $f->attr('checked', $this->currentData['overwriteNames'] ? 'checked' : '' );
            $f->notes = __("This option can cause problems if the affected child pages are part of the front end structure of the site. It may result in broken links, etc.");
            $childEditSet->add($f);
        }

        // Make new pages active in other languages?
        if($this->wire('languages')) {
            $f = $this->wire('modules')->get("InputfieldCheckbox");
            $f->name = "activateOtherLanguages";
            $f->showIf = "edit_mode.count>0, edit_mode!=edit, edit_mode!=export, edit_mode!=lister";
            $f->label = __('Activate New Pages in Other Languages');
            $f->columnWidth = 33;
            $f->attr('checked', $this->wire('session')->activateOtherLanguages === 0 ? '' : 'checked');
            $childEditSet->add($f);

            $f = $this->wire('modules')->get("InputfieldSelect");
            $f->name = "languageToPopulate";
            $f->showIf = "edit_mode.count>0, edit_mode!=edit, edit_mode!=export, edit_mode!=lister";
            $f->label = __('Choose the language to populate');
            $f->required = true;
            $f->columnWidth = 34;
            $f->value = $this->wire('languages')->get('default');
            foreach($this->wire('languages') as $lang) {
                $f->addOption($lang->name);
            }
            $childEditSet->add($f);

            $f = $this->wire('modules')->get("InputfieldCheckbox");
            $f->name = "populateToAllLanguages";
            $f->showIf = "edit_mode.count>0, edit_mode!=edit, edit_mode!=export, edit_mode!=lister, languageToPopulate=default";
            $f->label = __('Populate field content to all languages');
            $f->description = __('Only check this if this if the default language is the first language you are importing.');
            $f->columnWidth = 33;
            $childEditSet->add($f);
        }

        // User CSV Import Settings
        if((isset($this->currentData['allowOverrideCsvImportSettings']) && $this->currentData['allowOverrideCsvImportSettings']) && (in_array('add', $this->currentData['editModes']) || in_array('update', $this->currentData['editModes']) || in_array('replace', $this->currentData['editModes']))) {

            $csvSettingsFieldset = $this->wire('modules')->get("InputfieldFieldset");
            $csvSettingsFieldset->label = __("CSV Import Settings");
            $csvSettingsFieldset->showIf = "edit_mode=add|update|replace";
            //if all CSV settings are the same as defaults (page or module level), then collapse
            if($fieldSeparator == $this->currentData['csvImportFieldSeparator'] && $fieldEnclosure == $this->currentData['csvImportFieldEnclosure'] && $ignoreFirstRow == $this->currentData['ignoreFirstRow']) $csvSettingsFieldset->collapsed = Inputfield::collapsedYes;
            $childEditSet->add($csvSettingsFieldset);

            $f = $this->wire('modules')->get("InputfieldText");
            $f->name = 'userCsvImportFieldSeparator';
            $f->label = __('CSV fields separated with');
            $f->showIf = "edit_mode=add|update|replace";
            $f->notes = __('For tab separated, enter: tab');
            $f->value = $this->wire('session')->userCsvImportFieldSeparator ? $this->wire('session')->userCsvImportFieldSeparator : $this->currentData['csvImportFieldSeparator'];
            $f->columnWidth = 33;
            $csvSettingsFieldset->append($f);

            $f = $this->wire('modules')->get("InputfieldText");
            $f->name = 'userCsvImportFieldEnclosure';
            $f->label = __('CSV field enclosure');
            $f->showIf = "edit_mode=add|update|replace";
            $f->value = $this->wire('session')->userCsvImportFieldEnclosure ? $this->wire('session')->userCsvImportFieldEnclosure : $this->currentData['csvImportFieldEnclosure'];
            $f->columnWidth = 34;
            $csvSettingsFieldset->append($f);

            $f = $this->wire('modules')->get("InputfieldCheckbox");
            $f->attr('name', 'userIgnoreFirstRow');
            $f->label = __('CSV ignore the first row');
            $f->description = __('Use this if the first row contains column/field labels.');
            $f->showIf = "edit_mode=add|update|replace";
            //using $this->wire('session')->userCsvImportFieldSeparator in the ternary conditional because when $this->wire('session')->userIgnoreFirstRow is not "1", it doesn't seem to exist, even with isset!
            //this is fine, because once page has been saved, with any of these overrides, we have values for all in the session variable
            $f->attr('checked', ($this->wire('session')->userCsvImportFieldSeparator ? $this->wire('session')->userIgnoreFirstRow : $this->currentData['ignoreFirstRow']) ? 'checked' : '' );
            $f->columnWidth = 33;
            $csvSettingsFieldset->append($f);
        }

        // Add
        if(in_array('add', $this->currentData['editModes'])) {

            $csvFieldset = $this->wire('modules')->get("InputfieldFieldset");
            $csvFieldset->label = $this->currentData['addModeTitle'];
            $csvFieldset->description = $this->currentData['addModeDescription'];
            $csvFieldset->notes = $this->currentData['addModeNotes'];
            $csvFieldset->showIf = "edit_mode=add";
            $childEditSet->add($csvFieldset);

            if(is_array($this->currentData['csvOptions']) && in_array('paste', $this->currentData['csvOptions']) || !$this->currentData['csvOptions']) {
                $f = $this->wire('modules')->get("InputfieldTextarea");
                $f->name = "childPagesAdd";
                $f->showIf = "edit_mode=add";
                $f->label = __("Text / Paste CSV");
                if($this->currentData['ignoreFirstRow']) $f->notes = __("If first row is 'Title' then the import settings were initially set to ignore first row of the entered data.");
                $f->attr('value', $this->currentData['ignoreFirstRow'] ? 'Title' : '');
                if(!$this->currentData['csvOptionsCollapsed'] || ($this->currentData['csvOptionsCollapsed'] && !in_array('paste',$this->currentData['csvOptionsCollapsed']))) $f->collapsed = Inputfield::collapsedYes;
                $csvFieldset->append($f);
            }

            if(is_array($this->currentData['csvOptions']) && in_array('link', $this->currentData['csvOptions'])) {
                $f = $this->wire('modules')->get("InputfieldURL");
                $f->name = "csvUrlAdd";
                $f->showIf = "edit_mode=add";
                $f->label = __("Enter URL to CSV file");
                $f->placeholder = __("URL to CSV file");
                $f->attr('value', '');
                if(!$this->currentData['csvOptionsCollapsed'] || ($this->currentData['csvOptionsCollapsed'] && !in_array('link',$this->currentData['csvOptionsCollapsed']))) $f->collapsed = Inputfield::collapsedYes;
                $csvFieldset->append($f);
            }

            if(is_array($this->currentData['csvOptions']) && in_array('upload', $this->currentData['csvOptions'])) {
                $f = $this->wire('modules')->get("InputfieldMarkup");
                $f->label = __("Upload CSV file");
                $f->name = 'csvUploadAdd';
                $f->showIf = "edit_mode=add";
                $f->value = "<input name='csvAddFile' type='file' />";
                if(!$this->currentData['csvOptionsCollapsed'] || ($this->currentData['csvOptionsCollapsed'] && !in_array('upload',$this->currentData['csvOptionsCollapsed']))) $f->collapsed = Inputfield::collapsedYes;
                $csvFieldset->add($f);
            }

        }

        // Update
        if(in_array('update', $this->currentData['editModes'])) {

            $csvFieldset = $this->wire('modules')->get("InputfieldFieldset");
            $csvFieldset->label = $this->currentData['updateModeTitle'];
            $csvFieldset->description = $this->currentData['updateModeDescription'];
            $csvFieldset->notes = $this->currentData['updateModeNotes'];
            $csvFieldset->showIf = "edit_mode=update";
            $childEditSet->add($csvFieldset);

            if(($this->currentData['csvOptions'] && in_array('paste', $this->currentData['csvOptions'])) || !$this->currentData['csvOptions']) {
                $f = $this->wire('modules')->get("InputfieldTextarea");
                $f->name = "childPagesUpdate";
                $f->showIf = "edit_mode=update";
                $f->label = __("Text / Paste CSV");
                if($this->currentData['ignoreFirstRow']) $f->notes = __("If first row is 'Title' then the import settings were initially set to ignore first row of the entered data.");
                if(!$this->currentData['matchByFirstField']) $f->attr('value', $this->childPages);
                if(!$this->currentData['csvOptionsCollapsed'] || ($this->currentData['csvOptionsCollapsed'] && !in_array('paste',$this->currentData['csvOptionsCollapsed']))) $f->collapsed = Inputfield::collapsedYes;
                $csvFieldset->append($f);
            }

            if($this->currentData['csvOptions'] && in_array('link', $this->currentData['csvOptions'])) {
                $f = $this->wire('modules')->get("InputfieldURL");
                $f->name = "csvUrlUpdate";
                $f->showIf = "edit_mode=update";
                $f->label = __("Enter URL to CSV file");
                $f->placeholder = __("URL to CSV file");
                $f->attr('value', '');
                if(!$this->currentData['csvOptionsCollapsed'] || ($this->currentData['csvOptionsCollapsed'] && !in_array('link',$this->currentData['csvOptionsCollapsed']))) $f->collapsed = Inputfield::collapsedYes;
                $csvFieldset->append($f);
            }

            if($this->currentData['csvOptions'] && in_array('upload', $this->currentData['csvOptions'])) {
                $f = $this->wire('modules')->get("InputfieldMarkup");
                $f->label = __("Upload CSV file");
                $f->name = 'csvUploadUpdate';
                $f->showIf = "edit_mode=update";
                $f->value = "<input name='csvUpdateFile' type='file' />";
                if(!$this->currentData['csvOptionsCollapsed'] || ($this->currentData['csvOptionsCollapsed'] && !in_array('upload',$this->currentData['csvOptionsCollapsed']))) $f->collapsed = Inputfield::collapsedYes;
                $csvFieldset->add($f);
            }

        }

        // Replace
        if(in_array('replace', $this->currentData['editModes'])) {

            $csvFieldset = $this->wire('modules')->get("InputfieldFieldset");
            $csvFieldset->label = $this->currentData['replaceModeTitle'];
            $csvFieldset->description = $this->currentData['replaceModeDescription'];
            $csvFieldset->notes = $this->currentData['replaceModeNotes'];
            $csvFieldset->showIf = "edit_mode=replace";
            $childEditSet->add($csvFieldset);

            if(is_array($this->currentData['csvOptions']) && in_array('paste', $this->currentData['csvOptions']) || !$this->currentData['csvOptions']) {
                $f = $this->wire('modules')->get("InputfieldTextarea");
                $f->name = "childPagesReplace";
                $f->showIf = "edit_mode=replace";
                $f->label = __("Text / Paste CSV");
                if($this->currentData['ignoreFirstRow']) $f->notes = __("If first row is 'Title' then the import settings were initially set to ignore first row of the entered data.");
                $f->attr('value', $this->childPages);
                if(!$this->currentData['csvOptionsCollapsed'] || ($this->currentData['csvOptionsCollapsed'] && !in_array('paste',$this->currentData['csvOptionsCollapsed']))) $f->collapsed = Inputfield::collapsedYes;
                $csvFieldset->append($f);
            }

            if(is_array($this->currentData['csvOptions']) && in_array('link', $this->currentData['csvOptions'])) {
                $f = $this->wire('modules')->get("InputfieldURL");
                $f->name = "csvUrlReplace";
                $f->showIf = "edit_mode=replace";
                $f->label = __("Enter URL to CSV file");
                $f->placeholder = __("URL to CSV file");
                $f->attr('value', '');
                if(!$this->currentData['csvOptionsCollapsed'] || ($this->currentData['csvOptionsCollapsed'] && !in_array('link',$this->currentData['csvOptionsCollapsed']))) $f->collapsed = Inputfield::collapsedYes;
                $csvFieldset->append($f);
            }

            if(is_array($this->currentData['csvOptions']) && in_array('upload', $this->currentData['csvOptions'])) {
                $f = $this->wire('modules')->get("InputfieldMarkup");
                $f->label = __("Upload CSV file");
                $f->name = 'csvUploadReplace';
                $f->showIf = "edit_mode=replace";
                $f->value = "<input name='csvReplaceFile' type='file' />";
                if(!$this->currentData['csvOptionsCollapsed'] || ($this->currentData['csvOptionsCollapsed'] && !in_array('upload',$this->currentData['csvOptionsCollapsed']))) $f->collapsed = Inputfield::collapsedYes;
                $csvFieldset->add($f);
            }

        }

        // Edit
        if(in_array('edit', $this->currentData['editModes'])) {
            $results = $this->wire('modules')->get('InputfieldMarkup');
            $results->attr('id', 'edit');
            $results->showIf = "edit_mode=edit";
            $results->label = $this->currentData['editModeTitle'];
            $results->description = $this->currentData['editModeDescription'];
            $results->notes = $this->currentData['editModeNotes'];
            $table = $this->wire('modules')->get('MarkupAdminDataTable');
            $headerRow = array(
                __('Sort'),
                __('Title'),
                __('Name'),
                __('Template'),
                __('Hidden'),
                __('Unpublished'),
                __('View'),
                __('Edit'),
                __('Delete')
            );
            if($this->wire('languages') && $this->wire('modules')->isInstalled("LanguageSupportPageNames") && !$this->wire('user')->language->isDefaultLanguage) array_splice($headerRow, 3, 0, 'Active');
            $table->headerRow($headerRow);
            $table->setSortable(false);
            $table->setEncodeEntities(false);
            $table->addClass('bceEditTable');

            //Build the table
            $allTemplateIds = array();
            foreach($this->wire('templates') as $template) $allTemplateIds[] = $template->id;
            $possibleTemplates = $pp->template->childTemplates ? $pp->template->childTemplates : $allTemplateIds;

            $defaultTemplateOptions = "<select name = 'templateId[new_0]'>";
            foreach($possibleTemplates as $templateId) {
                $t = $this->wire('templates')->get($templateId);
                if(is_null($t) || !$t->id || !$this->isAllowedTemplateAdd($t, $pp)) continue;
                $defaultTemplateOptions .= '<option value = "'.$t->id.'">' . ($t->label ? $t->label : $t->name) . '</option>';
            }
            $defaultTemplateOptions .= "</select>";

            $rowNum=1;
            //if no children already, set up initial table row with blank name and delete button cells
            if(count($pp->children("include=all"))==0) {
                $row = array(
                    "<i style='cursor:move' class='fa fa-arrows InputfieldChildTableRowSortHandle'></i>",
                    '<input id="" type="text" name="individualChildTitles[new_0]" value="" style="width:100%" />',
                    "",
                    $defaultTemplateOptions,
                    "",
                    "",
                    "",
                    "",
                    ""
                );
                if($this->wire('languages') && $this->wire('modules')->isInstalled("LanguageSupportPageNames") && !$this->wire('user')->language->isDefaultLanguage) array_splice($row, 3, 0, '');
                $table->row($row);
                $rowNum++;
            }

            foreach ($pp->children("include=all") as $cp) {

                if ($cp->is(Page::statusSystemID)) continue; //Don't display if it is a system page (404, admin, trash etc)

                $allowPublish = true;
                if(!$this->wire('user')->isSuperuser()) {
                    $publishPermission = $this->wire('permissions')->get('page-publish');
                    if($publishPermission->id && !$this->wire('user')->hasPermission('page-publish')) $allowPublish = false;
                }

                $templateOptions = '';

                //if template of current page is not an allowed template, we still need to list it so manually add
                if(!in_array($cp->template->id, $possibleTemplates)) $templateOptions .= '<option value = "'.$cp->template->id.'" selected>' . ($cp->template->label ? $cp->template->label : $cp->template->name) . '</option>';

                //populate select with possible templates to choose from
                foreach($possibleTemplates as $templateId) {
                    $t = $this->wire('templates')->get($templateId);
                    if(!$this->isAllowedTemplateEdit($templateId, $cp)) continue;
                    $templateOptions .= '<option value = "'.$t->id.'" '.($t->id == $cp->template->id ? 'selected' : '').'>' . ($t->label ? $t->label : $t->name) . '</option>';
                }
                $langid = '';
                $langInfo = '';
                $userLang = '';
                if($this->wire('languages')) {
                    $userLang = $this->wire('user')->language;
                    $langid = $userLang->isDefaultLanguage ? '' : $userLang->id;
                    $langInfo = ' data-langinfo="<table class=\'bceLanguages\'><tr><th>Language</th><th>Title</th><th>Name</th><th>Active</th></tr>';
                    foreach($this->wire('languages') as $lang) {
                        $langInfo .= '<tr><td>' . htmlspecialchars($lang->title, ENT_QUOTES, "UTF-8") . '</td><td>' . htmlspecialchars($this->getLanguageVersion($cp, 'title', $lang), ENT_QUOTES, "UTF-8") . '</td><td>' . $this->getLanguageVersion($cp, 'name', $lang) . '</td><td>' . ($lang->isDefaultLanguage ? 'default' : ($cp->get("status{$lang->id}") ? "&#10004;" : "&#x2718;")) . '</td></tr>';
                    }
                    $langInfo .= '</table>" ';
                }
                $row = array(
                    $pp->sortfield == 'sort' && $cp->sortable() ? "<i style='cursor:move'  class='fa fa-arrows InputfieldChildTableRowSortHandle'></i>" : "",
                    '<input id="'.$cp->id.'" type="text" name="individualChildTitles['.$cp->id.']" placeholder="'.htmlspecialchars($cp->title, ENT_QUOTES, "UTF-8").'" value="'.htmlspecialchars($this->getLanguageVersion($cp, 'title', $userLang, true), ENT_QUOTES, "UTF-8").'" style="width:calc(100% - 20px)" '. (!$cp->editable() ? " readonly" : "") . ' />' . (($this->wire('languages') && $this->wire('languages')->count() > 1 && $this->titleMultiLanguage && (($cp->title && !$cp->title->getLanguageValue($userLang)) || !$cp->title)) ? '&nbsp;<i class="fa fa-info-circle" title="No title for '.$userLang->title.'" style="color:#cccccc" aria-hidden="true"></i>' : ''),
                    '<span class="bcePageNameContainer"><span class="bcePageName" ' . $langInfo . ' id="name_'.$cp->id.'" style="' . ($cp->is(Page::statusUnpublished) ? 'text-decoration:line-through;' : '') . ($cp->is(Page::statusHidden) ? 'color:#8c8c8c;' : '') . '">'.$this->getLanguageVersion($cp, 'name', $userLang, true) . ($this->wire('modules')->isInstalled("LanguageSupportPageNames") && !$cp->localName($userLang) ? '</span>&nbsp;<i class="fa fa-info-circle" title="No name for '.$userLang->title.'" style="color:#cccccc; float:right" aria-hidden="true"></i>' : '') . '</span>',
                    $this->currentData['allowTemplateChanges'] && $this->wire('user')->hasPermission("page-template", $cp) ? '<select id="template_'.$cp->id.'" name = "templateId['.$cp->id.']">' . $templateOptions . '</select>' : '<span id="template_'.$cp->id.'">'.($cp->template->label ?: $cp->template->name).'</span>',
                    '<input id="hiddenStatus_'.$cp->id.'" class="hiddenStatus" name="hiddenStatus['.$cp->id.']" type="checkbox" '.($cp->is(Page::statusHidden) ? 'checked' : '').'/>',
                    '<input id="unpublishedStatus_'.$cp->id.'" class="unpublishedStatus" name="unpublishedStatus['.$cp->id.']" type="checkbox"'.($cp->is(Page::statusUnpublished) ? ' checked' : '').(!$allowPublish ? ' disabled' : '').'/>',
                    $cp->viewable() ? ' <a href="'.$cp->httpUrl.'" target="_blank"><i style="cursor:pointer" class="fa fa-eye"></i></a>' : '',
                    $cp->editable() ? '<a class="batchChildTableEdit" data-url="./?id='.$cp->id.($this->wire('languages') ? '&amp;lang='.$userLang->id : '').'&amp;modal=1" href="'.$cp->editUrl.'"><i style="cursor:pointer" class="fa fa-pencil"></i></a>' : '',
                    $cp->trashable() ? "<i style='cursor:pointer' class='fa fa-trash-o InputfieldChildTableRowDeleteLink'></i>" : ""
                );
                if($this->wire('languages') && $this->wire('modules')->isInstalled("LanguageSupportPageNames") && !$userLang->isDefaultLanguage) {
                    array_splice($row, 3, 0, '<input id="langActiveStatus_'.$cp->id.'" class="langActiveStatus" name="langActiveStatus['.$cp->id.']" type="checkbox"'.($cp->get("status$langid") ? ' checked' : '').'/>');
                }
                $table->row($row, array('class' => 'pid_'.$cp->id));
                $rowNum++;
            }

            $hiddenInfo = "
            <div id='defaultTemplates' style='display:none'>$defaultTemplateOptions</div>
            <input name='idsToDelete' class='InputfieldChildTableRowDelete' type='hidden' value='' />
            ";

            if($pp->addable()) {
                $button = $this->wire('modules')->get('InputfieldButton');
                $button->icon = 'plus-circle';
                $button->value = $this->_x('Add New', 'button');
                $button->attr('class', 'ui-button ui-widget ui-corner-all ui-state-default InputfieldChildTableAddRow');
            }

            $results->attr('value', $hiddenInfo . '<div class="batchChildTableContainer">' . $table->render() . '</div>' . ($pp->addable() ? $button->render() : ''));
            $childEditSet->append($results);
        }

        // Lister
        if(in_array('lister', $this->currentData['editModes']) && $this->wire('user')->hasPermission('page-lister')) {

            $listerFieldset = $this->wire('modules')->get("InputfieldFieldset");
            $listerFieldset->label = $this->currentData['listerModeTitle'];
            $listerFieldset->description = $this->currentData['listerModeDescription'];
            $listerFieldset->notes = $this->currentData['listerModeNotes'];
            $listerFieldset->showIf = "edit_mode=lister";
            $childEditSet->add($listerFieldset);

            $this->wire('modules')->get('JqueryCore')->use('iframe-resizer');
            $f = $this->wire('modules')->get('InputfieldMarkup');
            $f->markupFunction = function($inputfield) use($pp) {
                // bookmarks for Lister
                $inputfield->wire('modules')->includeModule('ProcessPageLister');
                $customDefaultFilter = $this->currentData['defaultFilter'] ? ", {$this->currentData['defaultFilter']}" : "";
                $bookmark = array(
                    'initSelector' => '',
                    'defaultSelector' => "parent={$pp->id}, include=all{$customDefaultFilter}",
                    'defaultSort' => $this->currentData['listerDefaultSort'],
                    'columns' => $this->currentData['listerColumns'],
                    'toggles' => array('noButtons'),
                    'allowBookmarks' => false,
                    'allowIncludeAll' => true,
                    'viewMode' => 4,
                    'editMode' => 4,
                    'editOption' => 2,
                );
                $id = "bce_".(int)$this->wire('input')->get->id."_children";
                $url = ProcessPageLister::addSessionBookmark($id, $bookmark) . '&modal=inline&minimal='.($this->currentData['listerConfigurable'] ? '0' : '1');
                if($url) return "
                    <iframe id='BatchChildEditorPageLister' scrolling='no' style='width:100%; border: none;' src='$url'></iframe>
                    <script>
                        $('#BatchChildEditorPageLister').iFrameResize({ });
                        setTimeout(\"$('#BatchChildEditorPageLister').contents().find('#head_button').hide();$('#BatchChildEditorPageLister').contents().find('#_ProcessListerResetTab').hide();$('#BatchChildEditorPageLister').contents().find('#_ProcessListerConfigTab').hide();\",2000);
                    </script>
                    ";
            };

            /*
            //if($this->wire('user')->hasPermission('page-lister')) {
                if($this->wire('modules')->isInstalled('ProcessPageLister')) {
                    $this->lister = $this->wire('modules')->get('ProcessPageListerPro');
                }
            //}



            if($this->lister) {
                $lister = $this->lister;
                //if(count($_GET)) $lister->sessionClear();
                $lister->initSelector = '';
                $lister->parent = $pp->id;
                $customDefaultFilter = $this->currentData['defaultFilter'] ? ", {$this->currentData['defaultFilter']}" : "";
                $lister->defaultSelector = "parent={$pp->id}, include=all{$customDefaultFilter}";
                $lister->defaultSort = $this->currentData['listerDefaultSort'];
                $lister->limit = 25;
                $lister->useColumnLabels = 1;
                //$lister->preview = false;
                $lister->columns = $this->currentData['listerColumns'];
                $lister->toggles = array('noButtons');
                $lister->allowBookmarks = false;
                $lister->allowIncludeAll = true;
                $lister->viewMode = 4;
                $lister->editMode = 2;
                $lister->editOption = 2;
                $f->value = $lister->execute();
            }*/

            $f->icon = 'search';
            $listerFieldset->add($f);
        }

        // Export
        if(in_array('export', $this->currentData['editModes']) && $pp->numChildren() > 0) {

            //if Process helper module is not installed, install it
            if(!$this->wire('modules')->isInstalled("ProcessChildrenCsvExport")) $this->wire('modules')->get("ProcessChildrenCsvExport");

            $exportFieldset = $this->wire('modules')->get("InputfieldFieldset");
            $exportFieldset->label = $this->currentData['exportModeTitle'];
            $exportFieldset->description = $this->currentData['exportModeDescription'];
            $exportFieldset->notes = $this->currentData['exportModeNotes'];
            $exportFieldset->showIf = "edit_mode=export";
            $childEditSet->add($exportFieldset);

            $allFields = array();
            foreach($pp->children("include=all") as $child) {
                foreach($child->fields as $cf) {
                    if(!in_array($cf, $allFields)) $allFields[] = $cf;
                }
            }

            if($this->currentData['allowOverrideCsvExportSettings']) {

                $f = $this->wire('modules')->get("InputfieldSelector");
                $f->attr('name', 'pagesToInclude');
                $f->label = __('Pages to Include');
                $f->description = __('Leave blank to automatically select all child pages&nbsp;(not hidden and published)');
                $f->initValue = "parent={$pp->id}";
                $f->value = $this->currentData['pagesToInclude'];
                $f->collapsed = Inputfield::collapsedBlank;
                $exportFieldset->add($f);

                $f = $this->wire('modules')->get("InputfieldAsmSelect");
                $f->name = "userExportFields";
                $f->showIf = "edit_mode=export";
                $f->label = __('Fields to export');
                $f->description = __('Choose and sort the fields to include in the CSV export');

                //system field labels
                foreach($this->data['systemFields'] as $systemField => $systemFieldLabel) {
                    $fieldLabels[$systemField] = $systemFieldLabel;
                }

                //custom template field labels for all child pages
                foreach($allFields as $pf) {
                    $fieldLabels[$pf->name] = $pf->label ? $pf->label : $pf->name;
                }

                //populate user override export field list from the page's Settings tab
                $populatedFields = array();
                if(in_array($settingsPage->id, $this->data['configurablePages']) && isset($this->data['pageSettings'][$settingsPage->id]['exportFields'])) {
                    foreach($this->data['pageSettings'][$settingsPage->id]['exportFields'] as $exportField) {
                        $populatedFields[] = $exportField;
                        if(isset($fieldLabels[$exportField])) $f->addOption($exportField, $fieldLabels[$exportField]);
                        $f->value = $exportField;
                    }
                }
                else {
                    foreach($allFields as $exportField) {
                        $populatedFields[] = $exportField->name;
                        if(isset($fieldLabels[$exportField->name])) $f->addOption($exportField->name, $fieldLabels[$exportField->name]);
                        $f->value = $exportField->name;
                    }
                }

                //all other fields not already populated
                //system fields
                foreach($this->data['systemFields'] as $systemField => $systemFieldLabel) {
                    if(!in_array($systemField, $populatedFields)) $f->addOption($systemField, $systemFieldLabel);
                }
                $allFields = array();
                foreach($pp->children("include=all") as $child) {
                    foreach($child->fields as $cf) {
                        if(!in_array($cf, $allFields)) $allFields[] = $cf;
                    }
                }

                //custom template fields for all child pages
                foreach($allFields as $pf) {
                    if(!in_array($pf->name, $populatedFields)) $f->addOption($pf->name, $pf->label ? $pf->label : $pf->name);
                }

                $exportFieldset->add($f);


                $f = $this->wire('modules')->get("InputfieldText");
                $f->name = 'export_column_separator';
                $f->label = __('Columns separated with');
                $f->notes = __('For tab separated, enter: tab');
                $f->value = $this->currentData['csvExportFieldSeparator'];
                $f->columnWidth = 33;
                $exportFieldset->add($f);

                $f = $this->wire('modules')->get("InputfieldText");
                $f->name = 'export_column_enclosure';
                $f->label = __('Column enclosure');
                $f->value = $this->currentData['csvExportFieldEnclosure'];
                $f->columnWidth = 34;
                $exportFieldset->add($f);

                $f = $this->wire('modules')->get("InputfieldText");
                $f->name = 'export_extension';
                $f->label = __('File extension');
                $f->value = $this->currentData['csvExportExtension'];
                $f->columnWidth = 33;
                $exportFieldset->add($f);

                $f = $this->wire('modules')->get("InputfieldText");
                $f->attr('name', 'export_multiple_values_separator');
                $f->label = __('Multiple values separator');
                $f->description = __('Separator for multiple values like Page fields, files/images, multiplier, etc.');
                $f->notes = __('Default is | Other useful options include \r for new lines when importing into Excel.');
                $f->value = $this->currentData['exportMultipleValuesSeparator'];
                $f->columnWidth = 33;
                $exportFieldset->add($f);

                $f = $this->wire('modules')->get("InputfieldCheckbox");
                $f->name = 'export_names_first_row';
                $f->label = __('Column labels');
                $f->label2 = 'Put column names in the first row';
                $f->attr('checked', $this->currentData['columnsFirstRow'] ? 'checked' : '' );
                $f->columnWidth = 34;
                $exportFieldset->add($f);

                $f = $this->wire('modules')->get("InputfieldCheckbox");
                $f->attr('name', 'format_export');
                $f->label = __('Format Export');
                $f->label2 = __('Turns on output formatting for exported values.');
                $f->notes = __('If you will be importing this back into ProcessWire, you should leave unchecked.');
                $f->attr('checked', $this->currentData['formatExport'] ? 'checked' : '' );
                $f->columnWidth = 33;
                $exportFieldset->add($f);

            }
            //if there is no user override then populate hidden so that js file can get these value and pass them to the Process helper module
            else {
                $f = $this->wire('modules')->get("InputfieldMarkup");
                $f->attr('name', 'csv_settings');
                $exportFields = $this->currentData['exportFields'] ? implode(',', $this->currentData['exportFields']) : implode(',', $allFields);
                $f->value = "
                <input id='Inputfield_exportFields' type='hidden' value='{$exportFields}' />
                <input id='Inputfield_pagesToInclude' type='hidden' value='{$this->currentData['pagesToInclude']}' />
                <input id='Inputfield_export_column_separator' type='hidden' value='{$this->currentData['csvExportFieldSeparator']}' />
                <input id='Inputfield_export_column_enclosure' type='hidden' value='{$this->currentData['csvExportFieldEnclosure']}' />
                <input id='Inputfield_export_extension' type='hidden' value='{$this->currentData['csvExportExtension']}' />
                <input id='Inputfield_export_multiple_values_separator' type='hidden' value='{$this->currentData['exportMultipleValuesSeparator']}' />
                <input id='Inputfield_format_export' type='hidden' value='{$this->currentData['formatExport']}' />
                <input id='Inputfield_export_names_first_row' type='hidden' value='{$this->currentData['columnsFirstRow']}' />
                ";
                $f->addClass('InputfieldHidden');
                $exportFieldset->add($f);
            }

            // hack to stop export button from floating to the right of the export options when using the Reno admin theme
            $f = $this->wire('modules')->get("InputfieldMarkup");
            $f->attr('name', 'break');
            $f->collapsed = Inputfield::collapsedYes;
            $f->value = "<br />";
            $exportFieldset->add($f);

            $f = $this->wire('modules')->get("InputfieldButton");
            $f->name = 'export_button';
            $f->value = $this->_x('Export as CSV', 'button');
            $f->attr('class', 'ui-button ui-widget ui-corner-all ui-state-default children_export_csv');
            $f->attr('data-pageid', (int) $this->wire('input')->id);
            $exportFieldset->add($f);

            $f = $this->wire('modules')->get("InputfieldMarkup");
            $f->attr('name', 'iframe');
            $f->collapsed = Inputfield::collapsedYes;
            $f->value = "<iframe id='download' src=''></iframe>";
            $exportFieldset->add($f);

        }

        return $childEditSet;
    }

    public function saveChildren(HookEvent $event) {

        // early exit if no mode has been selected
        if(!$this->wire('input')->post->edit_mode) return;

        // ProcessPageEdit's processInput function may go recursive, so we want to skip
        // the instances where it does that by checking the second argument named "level"
        $level = $event->arguments(1);
        if($level > 0) return;

        //set the current edit mode so it can be opened after saving the page
        $this->wire('session')->edit_mode = $this->wire('input')->post->edit_mode;

        //set the current activateOtherLanguages status so it is remembered for next operation
        $this->wire('session')->activateOtherLanguages = $this->wire('input')->post->activateOtherLanguages ? 1 : 0;

        $this->wire('session')->populateToAllLanguages = $this->wire('input')->post->populateToAllLanguages ? 1 : 0;
        $this->wire('session')->languageToPopulate = $this->wire('input')->post->languageToPopulate;


        $pp = $event->object->getPage();

        //populate currentData with pageSettings version if page has specific settings
        $this->currentData = in_array($pp->id, $this->data['configurablePages']) && isset($this->data['pageSettings'][$pp->id]) && $this->data['pageSettings'][$pp->id] ? $this->data['pageSettings'][$pp->id] : $this->data;

        if(isset($this->currentData['parentPage']) && $this->currentData['parentPage']) $pp = $this->wire('pages')->get($this->currentData['parentPage']);

        //get user CSV setting overrides if allowed
        if((isset($this->currentData['allowOverrideCsvImportSettings']) && $this->currentData['allowOverrideCsvImportSettings'])) {
            $this->currentData['csvImportFieldSeparator'] = $this->wire('session')->userCsvImportFieldSeparator;
            $this->currentData['csvImportFieldEnclosure'] = $this->wire('session')->userCsvImportFieldEnclosure;
            $this->currentData['ignoreFirstRow'] = $this->wire('session')->userIgnoreFirstRow;
        }

        //use the selected template or if none selected then it means there is only one childTemplate option [0], so use that
        $childTemplate = $this->wire('input')->post->childTemplate ? $this->wire('input')->post->childTemplate : $pp->template->childTemplates[0];

        //Replace Mode
        if($this->wire('input')->post->edit_mode == "replace") {

            $childPagesReplace = $this->processTextOrFile($this->wire('input')->post->childPagesReplace, $this->wire('input')->post->csvUrlReplace,'csvReplaceFile');

            //if theURL or CSV versions are empty, escape now to prevent unwanted deletion and recreation of pages
            //note that an empty textarea/paste option is valid - it provides a way to batch delete all child pages
            if($childPagesReplace == 'BCE-FILE-EMPTY') {
                $this->error($this->_("The CSV file was empty, so no pages can be created."));
                return;
            }
            //if content (textarea, URL or CSV upload) and the selected template hasn't changed, escape now to prevent unwanted deletion and recreation of pages
            if($childPagesReplace == $this->childPages && $this->wire('input')->post->childTemplate == $pp->children("include=all")->last()->template) return;


            if(!$this->currentData['disableContentProtection']) {
                $disableNote = $this->wire('user')->isSuperuser() ? __(" You can prevent this check by disabling Content Protection in the module config settings.") : '';
                foreach($pp->children("include=all") as $cp) {
                    if($cp->numChildren>0) {
                        $this->error($this->_("You cannot batch replace these child pages, because at least one page has a child page of its own. Try the edit option, or delete existing child pages first." . $disableNote));
                        return;
                    }
                    foreach($cp->fields as $cpfield) {
                        if($cpfield->name != 'title' && $cp->$cpfield !='') {
                            $this->error($this->_("You cannot batch replace these child pages, because at least one page has a field which is not empty. Try the edit option, or delete existing child pages first." . $disableNote));
                            return;
                        }
                    }
                }
            }
            // delete existing child pages
            foreach($pp->children as $child) {
                $this->currentData['trashOrDelete'] == 'delete' ? $child->delete() : $child->trash();
            }
            $this->createPages($childPagesReplace, $pp, $childTemplate);
        }
        //Update Mode
        elseif($this->wire('input')->post->edit_mode == "update") {

            $childPagesUpdate = $this->processTextOrFile($this->wire('input')->post->childPagesUpdate, $this->wire('input')->post->csvUrlUpdate,'csvUpdateFile');

            //if theURL or CSV versions are empty, escape now to prevent unwanted deletion and recreation of pages
            //note that an empty textarea/paste option is valid - it provides a way to batch delete all child pages
            if($childPagesUpdate == 'BCE-FILE-EMPTY') {
                $this->error($this->_("The CSV file was empty, so no pages can be created."));
                return;
            }

            //if content (textarea, URL or CSV upload) and the selected template hasn't changed, escape now to prevent unwanted saving of pages
            if($childPagesUpdate == $this->childPages && $this->wire('input')->post->childTemplate == $pp->children("include=all")->last()->template) return;

            $this->createPages($childPagesUpdate, $pp, $childTemplate);
        }
        //Add mode
        elseif($this->wire('input')->post->edit_mode == "add") {
            $childPagesAdd = $this->processTextOrFile($this->wire('input')->post->childPagesAdd, $this->wire('input')->post->csvUrlAdd, 'csvAddFile');
            $this->createPages($childPagesAdd, $pp, $childTemplate);
        }
        //Edit Mode
        else {
            //delete any pages marked for deletion
            if($this->wire('input')->post->idsToDelete!='') {
                $idsToDelete = explode(',', rtrim($this->wire('input')->post->idsToDelete, ','));
                foreach($idsToDelete as $id) {
                    $ptod = $this->wire('pages')->get($id);

                    if(!$this->currentData['disableContentProtection']) {
                        $disableNote = $this->wire('user')->isSuperuser() ? __(" You can prevent this check by disabling Content Protection in the module config settings.") : '';
                        if($ptod->numChildren>0) {
                            $this->error($this->_("You cannot delete this child page, because it has a child page of its own." . $disableNote));
                            return;
                        }

                        if(is_object($ptod->fields) && !empty($ptod->fields)) {
                            foreach($ptod->fields as $ptodfield) {
                                if($ptodfield->name != 'title' && $ptod->$ptodfield !='') {
                                    $this->error($this->_("You cannot delete this child page, because it has a field which is not empty." . $disableNote));
                                    return;
                                }
                            }
                        }
                    }

                    if($id!='' && $ptod->trashable()) {
                        $this->currentData['trashOrDelete'] == 'delete' ? $this->wire('pages')->delete($ptod, true) : $this->wire('pages')->trash($ptod);
                    }
                }
            }

            $langid = '';
            if($this->wire('languages')) {
                if(!$this->wire('user')->language->isDefaultLanguage) {
                    $langid = $this->wire('user')->language->id;
                }
            }

            $i=0;
            foreach($this->wire('input')->post->individualChildTitles as $id => $childTitle) {
                if(isset($idsToDelete) && in_array($id, $idsToDelete)) continue; //ignore pages that have just been deleted
                $childTitle = trim($this->wire('sanitizer')->text($childTitle));
                $i++;
                // new page
                if(strpos($id, 'new') !== FALSE) {
                    if($childTitle == '') continue; // in case someone clicked add Page, but left it blank
                    if((is_array($pp->template->childTemplates) && !empty($pp->template->childTemplates) && !in_array($this->wire('input')->post->templateId[$id], $pp->template->childTemplates)) || $this->wire('templates')->get($this->wire('input')->post->templateId[$id])->noParents) {
                        $this->error($this->_("Some pages could not be created due to template family settings."));
                        continue;
                    }

                    $cp = $this->newPage($pp, $this->wire('input')->post->templateId[$id]);
                    if(isset($this->wire('input')->post->langActiveStatus[$id])) $cp->set("status$langid", 1);
                    if(isset($this->wire('input')->post->hiddenStatus[$id])) $cp->addStatus(Page::statusHidden);
                    if(isset($this->wire('input')->post->unpublishedStatus[$id])) $cp->addStatus(Page::statusUnpublished);
                }
                // existing page
                else {

                    $cp = $this->wire('pages')->get($id);

                    if($this->currentData['allowTemplateChanges'] && $this->wire('user')->hasPermission("page-template", $cp)) $cp->template = $this->wire('input')->post->templateId[$cp->id];

                    if(!is_null($this->wire('input')->post->langActiveStatus)) {
                        if(array_key_exists($cp->id, $this->wire('input')->post->langActiveStatus)) {
                            $cp->set("status$langid", 1);
                        }
                        else {
                            $cp->set("status$langid", 0);
                        }
                    }

                    if(!is_null($this->wire('input')->post->hiddenStatus) &&
                        array_key_exists($cp->id, $this->wire('input')->post->hiddenStatus)) {
                        $cp->addStatus(Page::statusHidden);
                    }
                    else {
                        $cp->removeStatus(Page::statusHidden);
                    }

                    if(!is_null($this->wire('input')->post->unpublishedStatus) &&
                        array_key_exists($cp->id, $this->wire('input')->post->unpublishedStatus)) {
                        $cp->addStatus(Page::statusUnpublished);
                    }
                    else {
                        $cp->removeStatus(Page::statusUnpublished);
                    }

                    //if($i==1 && !$this->wire('input')->post->childTemplate) $childTemplate = $cp->template->name; //get the template of the first child in case we need it to assign to a newly added page
                }

                $cp->title = $childTitle;

                // if new set default language to same title as current language title
                if($this->titleMultiLanguage && strpos($id, 'new') !== FALSE && $childTitle != '') $cp->setLanguageValue('default', 'title', $childTitle);

                if($cp->isChanged('title') && ($this->wire('input')->post->userOverwriteNames || (!$this->wire('input')->post->userOverwriteNames && !$this->currentData['allowOverrideOverwriteNames'] && $this->currentData['overwriteNames']))) {

                    $n = 0;
                    $pageName = $this->wire('sanitizer')->pageName($childTitle, Sanitizer::translate);
                    $matchedChildId = null;
                    do {
                        $name = $pageName . ($n ? "-$n" : '');
                        // see if another page already has the same name
                        if($this->wire('languages') && $this->wire('modules')->isInstalled("LanguageSupportPageNames") && !$this->wire('user')->language->isDefaultLanguage) {
                            $child = $pp->child("name$langid=$name");
                        }
                        else {
                            $child = $pp->child("name=$name");
                        }
                        if($child->id) $matchedChildId = $child->id;
                        $n++;
                    } while($child->id);

                    if($name != '' && $matchedChildId !== $cp->id) {
                        if($this->wire('languages') && $this->wire('modules')->isInstalled("LanguageSupportPageNames") && !$this->wire('user')->language->isDefaultLanguage) {
                            $cp->{"name$langid"} = $name;
                        }
                        else {
                            $cp->name = $name;
                        }
                    }
                }
                $cp->sort = $i;

                if($cp->isChanged()) {
                    $cp->of(false);
                    $cp->save();
                }
            }
        }
    }


    public function processTextOrFile($str, $link, $fileInputName) {

        //CSV file upload
        if(isset($_FILES[$fileInputName]['name']) && $_FILES[$fileInputName]['name'] !== '') {

            $csv_file_extension = pathinfo($_FILES[$fileInputName]["name"], PATHINFO_EXTENSION);

            if($csv_file_extension == 'csv' || $csv_file_extension == 'txt' || $csv_file_extension == 'tsv') {
                $childPagesContent = file_get_contents($_FILES[$fileInputName]["tmp_name"]);
                if($childPagesContent == '') return 'BCE-FILE-EMPTY';
            }
            else {
                $this->error($this->_("That is not an allowed file extension for a CSV import. Try again with a .csv, .tsv, or .txt file"));
            }

            unlink($_FILES[$fileInputName]["tmp_name"]);
        }
        elseif($link != '') {
            $childPagesContent = file_get_contents($link);
            if($childPagesContent == '') return 'BCE-FILE-EMPTY';
        }
        elseif($str != '') $childPagesContent = $str;
        else return;

        return mb_check_encoding($childPagesContent, 'UTF-8') ? $childPagesContent : utf8_encode($childPagesContent);
    }



    public function createPages($childPages, $pp, $childTemplate) {

        //populate currentData with pageSettings version if page has specific settings
        $this->currentData = in_array($pp->id, $this->data['configurablePages']) && isset($this->data['pageSettings'][$pp->id]) && $this->data['pageSettings'][$pp->id] ? $this->data['pageSettings'][$pp->id] : $this->data;
        if(isset($this->currentData['parentPage']) && $this->currentData['parentPage']) $pp = $this->wire('pages')->get($this->currentData['parentPage']);

        //get user CSV setting overrides if allowed
        if((isset($this->currentData['allowOverrideCsvImportSettings']) && $this->currentData['allowOverrideCsvImportSettings'])) {
            $this->currentData['csvImportFieldSeparator'] = $this->wire('session')->userCsvImportFieldSeparator = $this->wire('input')->post->userCsvImportFieldSeparator;
            $this->currentData['csvImportFieldEnclosure'] = $this->wire('session')->userCsvImportFieldEnclosure = $this->wire('input')->post->userCsvImportFieldEnclosure;
            $this->currentData['ignoreFirstRow'] = $this->wire('session')->userIgnoreFirstRow = $this->wire('input')->post->userIgnoreFirstRow;
        }

        if($childPages == '') return;

        // if there is no new line at the end, add one to fix issue if last item in CSV row has enclosures but others don't
        if(substr($childPages, -1) != "\r" && substr($childPages, -1) != "\n") $childPages .= PHP_EOL;

        require_once __DIR__ . '/parsecsv-for-php/parsecsv.lib.php';

        $childPagesArray = new parseCSV();
        $childPagesArray->encoding('UTF-16', 'UTF-8');
        $childPagesArray->heading = $this->currentData['ignoreFirstRow'];
        $childPagesArray->delimiter = $this->currentData['csvImportFieldSeparator'] == "tab" ? chr(9) : $this->currentData['csvImportFieldSeparator'];
        $childPagesArray->enclosure = $this->currentData['csvImportFieldEnclosure'];
        $childPagesArray->parse($childPages);

        //if defined, get field pairings
        if(isset($this->data['pageSettings'][$pp->id]['fieldPairings']) && $this->data['pageSettings'][$pp->id]['fieldPairings'] != '') {
            $convertedFieldPairings = $this->convertFieldPairings($this->currentData['fieldPairings']);
            //if it exists, move title to first position in array but maintain keys for pairing to CSV columns
            $titleKey = array_search('title', $convertedFieldPairings);
            if($titleKey) $convertedFieldPairings = array($titleKey => $convertedFieldPairings[$titleKey]) + $convertedFieldPairings;
        }

        //iterate through rows in childpages data (text/paste, URL, or uploaded CSV)
        $x=0;
        $database = $this->wire('database');
        $useTransaction = $this->supportsTransaction();
        try {
            if($useTransaction) {
                $database->beginTransaction();
            }
            foreach($childPagesArray->data as $row) {

                // parsecsv-for-php library converts numerical array to associative when heading/ignoreFirstRow is true
                // which we don't want, so convert back back to numerical
                if($this->currentData['ignoreFirstRow']) $row = array_values($row);

                $newPage = false;
                $i=0;
                $x++;

                //if fields pairings are not defined, then make sure the field count matches the number of fields in the selected child template
                if(!isset($this->data['pageSettings'][$pp->id]['fieldPairings']) || $this->data['pageSettings'][$pp->id]['fieldPairings'] == '') {
                    //only if greater than one, because one column in each row is just for setting the title/name
                    if(count($row) > 1 && count($row) != count($this->wire('templates')->get($childTemplate)->fields)) {
                        $this->error($this->_("The number of columns/fields in your CSV do not match the number of fields in the template. Nothing can be safely imported. If you need to exclude certain fields, make sure the CSV has blank values for the excluded fields."));
                        return;
                    }
                }
                //if field pairings are defined, check that the field/column count in the CSV is at least as many as the number of defined pairings
                //if field pairings are defined then we don't allow titles only (unless that is the only pairing defined), hence no check for count($row) > 1
                else {
                    if(count($row) < count($convertedFieldPairings)) {
                        $this->error($this->_("The number of columns/fields in your CSV is not enough for number of fields defined for this import. Nothing can be safely imported."));
                        return;
                    }
                }

                //if first loop and replace mode then now it's safe to delete all existing child pages because above checks for field pairings have passed
                if($x==1 && $this->wire('input')->post->edit_mode == "replace") {
                    foreach($pp->children("include=all") as $cp) {
                        if(!$cp->is(Page::statusSystemID) && $cp->trashable()) {
                            $this->currentData['trashOrDelete'] == 'delete' ? $this->wire('pages')->delete($cp, true) : $this->wire('pages')->trash($cp);
                        }
                    }
                }

                //create new or edit existing child pages
                //if fieldPairings is defined and this is first column, then set name/title and create new / get existing page
                if($i==0 && $this->currentData['fieldPairings'] != '') {
                    // if title key exists, then make sure it is populated
                    // not necessary in update mode only setups so it may be not set
                    if($titleKey && $row[$titleKey-1] == '') continue;
                    //update mode so re-title/re-name existing page
                    if($this->wire('input')->post->edit_mode == "update") {
                        $np = $this->currentData['matchByFirstField'] ? $pp->child($convertedFieldPairings[$i+1]."=".$this->wire('sanitizer')->selectorValue($row[0]).", include=all") : $pp->children("include=all")->eq($x-1);
                        if(!$np->id) {
                            if($this->currentData['addNewNoMatch']) {
                                $newPage = true;
                                $np = $this->newPage($pp, $childTemplate);
                                $np->of(false);
                                $np->save();
                            }
                            else {
                                continue;
                            }
                        }
                        // page already exists, but update template if allowed / requested
                        elseif($this->wire('input')->post->updateTemplateExistingChildren) {
                            $np->template = $childTemplate;
                            $np->save();
                        }
                        if($this->wire('input')->post->userOverwriteNames || (!$this->wire('input')->post->userOverwriteNames && !$this->currentData['allowOverrideOverwriteNames'] && $this->currentData['overwriteNames'])) {
                            if($titleKey) $this->setPageTitleOrName('name', trim($this->wire('sanitizer')->pageName($row[$titleKey-1], Sanitizer::translate)), $np, $newPage);
                        }
                    }
                    //not update mode, so create new page
                    else {
                        $newPage = true;
                        $np = $this->newPage($pp, $childTemplate);
                    }

                    if($titleKey) {
                        if(!$this->currentData['noCaseUpdate'] && $np->title !== trim($this->wire('sanitizer')->text($row[$titleKey-1]))) {
                            $this->setPageTitleOrName('title', trim($this->wire('sanitizer')->text($row[$titleKey-1])), $np, $newPage);
                        }
                    }

                }

                //creating pages and populating fields
                foreach($row as $childFieldValue) {
                    $childFieldValue = trim($childFieldValue);
                    //if fieldPairings is defined now populate the rest of the fields
                    if($this->currentData['fieldPairings'] != '') {
                        //populate new page with rest of the field values
                        if(isset($convertedFieldPairings[$i+1]) && $convertedFieldPairings[$i+1] !== 'title') { // no need to redefine the title field value
                            //check for subfields (eg textareas pro field) with a period (.) separator
                            if (strpos($convertedFieldPairings[$i+1],'.')) {
                                $fieldParts = explode('.', $convertedFieldPairings[$i+1]);
                                $np->{$fieldParts[0]}->{$fieldParts[1]} = $childFieldValue;
                                if($np->isChanged($fieldParts[0])) {
                                    $np->of(false);
                                    $np->save($fieldParts[0]);
                                }
                            }
                            else {
                                $this->populateField($np, $convertedFieldPairings[$i+1], $childFieldValue);
                            }
                        }
                    }
                    // else field pairings not defined, so match all fields in order
                    else {
                        // first item is the page title so create new page
                        if($i==0) {
                            if($childFieldValue == '') continue;
                            // update mode so re-title/re-name existing page
                            if($this->wire('input')->post->edit_mode == "update") {
                                $np = $pp->children("include=all")->eq($x-1);
                                // update template if allowed / requested
                                if($np && $np->id && $this->wire('input')->post->updateTemplateExistingChildren) {
                                    $np->template = $childTemplate;
                                    $np->save();
                                }
                                if($this->wire('input')->post->userOverwriteNames || (!$this->wire('input')->post->userOverwriteNames && !$this->currentData['allowOverrideOverwriteNames'] && $this->currentData['overwriteNames'])) {
                                    if($np && $np->id) {
                                        $this->setPageTitleOrName('name', trim($this->wire('sanitizer')->pageName($childFieldValue, Sanitizer::translate)), $np, $newPage);
                                    }
                                    // for new pages added at the end when using Update mode
                                    else {
                                        if(!$pp->addable()) continue; //update mode is available to users without add permission, but they shouldn't be able to add new pages at the end
                                        $newPage = true;
                                        $np = $this->newPage($pp, $childTemplate);
                                    }
                                }
                            }
                            //not update mode, so create new page
                            else {
                                $newPage = true;
                                $np = $this->newPage($pp, $childTemplate);
                            }

                            $this->setPageTitleOrName('title', trim($this->wire('sanitizer')->text($childFieldValue)), $np, $newPage);

                            //populate numeric array of field names
                            $fieldsArray = array();
                            foreach($np->fields as $f) $fieldsArray[] = $f->name;
                        }
                        //populate new page with rest of the field values
                        else {
                            $this->populateField($np, $fieldsArray[$i], $childFieldValue);
                        }
                    }
                    $i++;
                }
            }

            if($useTransaction) {
                $database->commit();
            }
        } catch(\Exception $e) {
            if($useTransaction) {
                $database->rollBack();
            }
        }
    }

    /**
     * Are transactions available with current DB engine (or table)?
     *
     * @param string $table Optionally specify a table to specifically check to that table
     * @return bool
     *
     */
    public function supportsTransaction($table = '') {
        $engine = '';
        if($table) {
            $query = $this->prepare('SHOW TABLE STATUS WHERE name=:name');
            $query->bindValue(':name', $table);
            $query->execute();
            if($query->rowCount()) {
                $row = $query->fetch(\PDO::FETCH_ASSOC);
                $engine = empty($row['engine']) ? '' : $row['engine'];
            }
            $query->closeCursor();
        } else {
            $engine = $this->wire('config')->dbEngine;
        }
        return strtoupper($engine) === 'INNODB';
    }

    private function setPageTitleOrName($titleOrName, $value, $np, $newPage) {
        // if new set default language to same title as current language title
        if($this->titleMultiLanguage && $newPage && $value != '') {
            $np->setLanguageValue('default', $titleOrName, $value);
        }
        if($this->titleMultiLanguage) {
            $langs = $this->wire('session')->populateToAllLanguages ? $this->wire('languages') : array($this->wire('session')->languageToPopulate);
            foreach($langs as $lang) {
                $np->setLanguageValue($lang, $titleOrName, $value);
            }
        }
        else {
            $np->{$titleOrName} = $value;
        }

        if($np->isChanged("title")) {
            $np->of(false);
            $np->save();
        }
    }

    private function populateField($np, $fieldName, $childFieldValue) {
        $f = $this->wire('fields')->get($fieldName);
        if($f->usePurifier) $childFieldValue = $this->wire('sanitizer')->purify($childFieldValue);
        if($f->type instanceof FieldtypePage) {
            $this->updatePageFields($f, $fieldName, $np, $childFieldValue);
        }
        elseif($f->type instanceof FieldtypeFile) {
            $this->updateFileFields($f, $fieldName, $np, $childFieldValue, $this->currentData['newImageFirst']);
        }
        elseif($f->type instanceof FieldtypeMultiplier) {
            $this->updateMultiplierFields($f, $fieldName, $np, $childFieldValue);
        }
        elseif($f->type instanceof FieldtypeMapMarker) {
            $this->updateMapMarkerFields($f, $fieldName, $np, $childFieldValue);
        }
        else {
            if($this->wire('languages') && $f->type instanceof FieldtypeLanguageInterface) {
                $langs = $this->wire('session')->populateToAllLanguages ? $this->wire('languages') : array($this->wire('session')->languageToPopulate);
                foreach($langs as $lang) {
                    $np->{$fieldName}->setLanguageValue($lang, $childFieldValue);
                }
            }
            else {
                $np->{$fieldName} = $childFieldValue;
            }
        }

        if($np->isChanged($fieldName)) {
            $np->of(false);
            $np->save($fieldName);
        }
    }

    private function updateMultiplierFields($f, $fieldsArrayItem, $np, $childFieldValue) {
        $importMultipleValuesSeparator = $this->getImportMultipleValuesSeparator();
        $np->of(false);
        $np->{$fieldsArrayItem} = explode($importMultipleValuesSeparator, $childFieldValue);
        $np->save($fieldsArrayItem);
    }

    private function updateMapMarkerFields($f, $fieldName, $np, $childFieldValue) {
        $importMultipleValuesSeparator = $this->getImportMultipleValuesSeparator();
        $np->of(false);
        $subfields = explode($importMultipleValuesSeparator, $childFieldValue);
        $np->$fieldName->address = $subfields[0];
        $np->$fieldName->lat = $subfields[1];
        $np->$fieldName->lng = $subfields[2];
        $np->$fieldName->zoom = $subfields[3];
        $np->$fieldName->status = $subfields[4] != '' ? $subfields[4] : '-100';
        $np->save($fieldName);
    }

    private function updateFileFields($f, $fieldsArrayItem, $np, $childFieldValue, $newImageFirst = false) {
        $childFieldValues = $childFieldValue;
        $importMultipleValuesSeparator = $this->getImportMultipleValuesSeparator();
        foreach(explode($importMultipleValuesSeparator, $childFieldValues) as $childFieldValue) {
            if(file_exists($childFieldValue) || strpos($childFieldValue,'//') !== false) {
                try {
                    $imageName = $this->wire('sanitizer')->pageName(pathinfo($childFieldValue, PATHINFO_BASENAME), Sanitizer::translate);
                    $existingImage = $np->$fieldsArrayItem->get("name=".$this->wire('sanitizer')->selectorValue($imageName));
                    if($this->wire('fields')->get($fieldsArrayItem)->overwrite && $existingImage) {
                        $np->of(false);
                        $np->{$fieldsArrayItem}->remove($existingImage);
                        $np->save($fieldsArrayItem);
                    }
                    if($this->wire('fields')->get($fieldsArrayItem)->maxFiles == 0 || $np->$fieldsArrayItem->count() < $this->wire('fields')->get($fieldsArrayItem)->maxFiles) {
                        $np->$fieldsArrayItem->add($childFieldValue);
                        if($newImageFirst && $np->$fieldsArrayItem->count() > 0) {
                            $np->$fieldsArrayItem->insertBefore($np->$fieldsArrayItem->last(), $np->$fieldsArrayItem->first());
                        }
                    }
                }
                catch (Exception $e) {
                    //image must not be available at remote URL
                }
            }
        }
    }

    private function updatePageFields($f, $fieldsArrayItem, $np, $childFieldValue) {
        $inputfield = $f->getInputfield($np);
        $options = $inputfield->getSelectablePages($np);
        $importMultipleValuesSeparator = $this->getImportMultipleValuesSeparator();
        if($f->derefAsPage === 0) {
            foreach(explode($importMultipleValuesSeparator, $childFieldValue) as $title) {
                $pageMatch = null;
                // if ID supplied instead of actually a title
                if(is_numeric($title)) {
                    $pageMatch = $this->wire('pages')->get("id=$options, id={$this->wire('sanitizer')->selectorValue($title)}");
                }
                // now if title is not numeric, or if ID check didn't match (maybe numeric title was actually a title, not an ID)
                if(!is_numeric($title) || !$pageMatch->id) {
                    $pageMatch = $this->wire('pages')->get("id=$options, title={$this->wire('sanitizer')->selectorValue($title)}");
                }
                if(!$pageMatch->id && $title!='') {
                    $newPageFieldPage = $this->createNewPageFieldPage($title, $options);
                    $np->$fieldsArrayItem->add($newPageFieldPage);
                }
                else {
                    $np->$fieldsArrayItem->add($pageMatch);
                }
            }
        }
        else {
            $pageMatch = $this->wire('pages')->get("id=$options, title={$this->wire('sanitizer')->selectorValue($childFieldValue)}");
            if(!$pageMatch->id && $childFieldValue!='') {
                $newPageFieldPage = $this->createNewPageFieldPage($childFieldValue, $options);
                $np->$fieldsArrayItem = $newPageFieldPage;
            }
            else {
                $np->$fieldsArrayItem = $pageMatch;
            }
        }
        return $np;
    }

    private function createNewPageFieldPage($title, $options) {
        $newPageFieldPage = new Page();
        $newPageFieldPage->template = $options->first()->template;
        $newPageFieldPage->parent = $options->first()->parent;
        $newPageFieldPage->title = $this->wire('sanitizer')->text($title);
        $newPageFieldPage->save();
        return $newPageFieldPage;
    }

    private function getImportMultipleValuesSeparator() {
        if($this->currentData['importMultipleValuesSeparator'] == '\r') {
            $importMultipleValuesSeparator = chr(13);
        }
        elseif($this->currentData['importMultipleValuesSeparator'] == '\n') {
            $importMultipleValuesSeparator = chr(10);
        }
        else {
            $importMultipleValuesSeparator = $this->currentData['importMultipleValuesSeparator'];
        }
        return $importMultipleValuesSeparator;
    }

    /**
     * Return an InputfieldsWrapper of Inputfields used to configure the class
     *
     * @param array $data Array of config values indexed by field name
     * @return InputfieldsWrapper
     *
     */
    public function getModuleConfigInputfields(array $data) {

        $data = array_merge(self::getDefaultData(), $data);

        $wrapper = new InputfieldWrapper();

        $fieldset = $this->wire('modules')->get("InputfieldFieldset");
        $fieldset->attr('id', 'batch_child_editor_where_available_fieldset');
        $fieldset->label = __("Where editing tools are available and separately configurable");
        $fieldset->collapsed = Inputfield::collapsedYes;
        $wrapper->add($fieldset);

        $fieldEnabledTemplates = $this->wire('modules')->get('InputfieldAsmSelect');
        $fieldEnabledTemplates->attr('name+id', 'enabledTemplates');
        $fieldEnabledTemplates->label = __('Enabled templates', __FILE__);
        $fieldEnabledTemplates->columnWidth = 33;
        $fieldEnabledTemplates->description = __("The batch editing option will only be available for the selected templates.\nLeave blank to allow all templates.", __FILE__);
        $fieldEnabledTemplates->setAsmSelectOption('sortable', false);

        // populate with all available templates
        foreach($this->wire('templates') as $t) {
            // filter out system templates
            if(!($t->flags & Template::flagSystem)) $fieldEnabledTemplates->addOption($t->name);
        }
        if(isset($data['enabledTemplates'])) $fieldEnabledTemplates->value = $data['enabledTemplates'];
        $fieldset->add($fieldEnabledTemplates);


        $fieldEnabledPages = $this->wire('modules')->get('InputfieldPageListSelectMultiple');
        $fieldEnabledPages->attr('name+id', 'enabledPages');
        $fieldEnabledPages->label = __('Enabled pages', __FILE__);
        $fieldEnabledPages->columnWidth = 34;
        $fieldEnabledPages->description = __("The batch editing option will only be available for the selected pages.\nLeave blank to allow all pages.", __FILE__);
        if(isset($data['enabledPages'])) $fieldEnabledPages->value = $data['enabledPages'];
        $fieldset->add($fieldEnabledPages);

        $fieldConfigurablePages = $this->wire('modules')->get('InputfieldPageListSelectMultiple');
        $fieldConfigurablePages->attr('name+id', 'configurablePages');
        $fieldConfigurablePages->label = __('Configurable pages', __FILE__);
        $fieldConfigurablePages->columnWidth = 33;
        $fieldConfigurablePages->description = __("Selected pages will have their own config settings for Batch Child Editor - these will be added to the page's Settings tab. All other pages will use the config settings below.", __FILE__);
        if(isset($data['configurablePages'])) $fieldConfigurablePages->value = $data['configurablePages'];
        $fieldset->add($fieldConfigurablePages);

        $this->buildCoreSettings($data, $wrapper);

        return $wrapper;
    }


    public function convertFieldPairings ($fieldPairings) {
        //convert to array of column numbers and PW field names
        $temp = explode("\r\n", $fieldPairings);
        $convertedFieldPairings = array();
        foreach ($temp as $value) {
            $array = explode(':', str_replace(' ', '', $value));
            $array[1] = trim($array[1], '"');
            $convertedFieldPairings[$array[0]] = $array[1];
        }
        return $convertedFieldPairings;
    }


    private function getLanguageVersion($p, $fieldName, $lang, $showDefault = false) {
        if($this->wire('languages')) {
            $p->of(false);
            $result = '';
            if($fieldName == 'name') {
                if($this->wire('modules')->isInstalled("LanguageSupportPageNames")) {
                    $result = $p->localName($lang);
                }
                elseif($lang->isDefaultLanguage) {
                    $result = $p->$fieldName;
                }
            }
            elseif($fieldName == 'title') {
                if(!$this->wire('modules')->isInstalled("FieldtypePageTitleLanguage") || !$this->wire('fields')->get('title')->type instanceof FieldtypePageTitleLanguage) {
                    $result = $lang->isDefaultLanguage ? $p->$fieldName : '';
                }
                elseif(is_object($p->$fieldName)) {
                    $result = $p->$fieldName->getLanguageValue($lang);
                }
                else {
                    $result = $p->$fieldName;
                }
            }
            $result = $result == '' && $showDefault ? $p->$fieldName : $result;
            if($fieldName == 'name') $result = str_replace(array(' ', '-'), array('&nbsp;', '&#8209;'), $result);
            return $result;
        }
        else {
            return $p->$fieldName;
        }
    }

    /**
     * Returns an array of templates that are allowed to be used here
     *
     */
    protected function getAllowedTemplatesEdit($cp) {

        if(is_array($this->allowedTemplatesEdit)) return $this->allowedTemplatesEdit;

        $templates = array();
        $user = $this->wire('user');
        $isSuperuser = $user->isSuperuser();
        $page = $cp;
        $parent = $page->parent;
        $parentEditable = ($parent->id && $parent->editable());

        // current page template is assumed, otherwise we wouldn't be here
        $templates[$page->template->id] = $page->template;

        // check if they even have permission to change it
        if(!$user->hasPermission('page-template', $page) || $page->template->noChangeTemplate) {
            $this->allowedTemplatesEdit = $templates;
            return $templates;
        }

        $allTemplates = count($this->predefinedTemplates) ? $this->predefinedTemplates : $this->wire('templates');

        foreach($allTemplates as $template) {

            if(isset($templates[$template->id])) continue;

            if($template->flags & Template::flagSystem) {
                // if($template->name == 'user' && $parent->id != $this->wire('config')->usersPageID) continue;
                if(in_array($template->id, $this->wire('config')->userTemplateIDs) && !in_array($parent->id, $this->wire('config')->usersPageIDs)) continue;
                if($template->name == 'role' && $parent->id != $this->wire('config')->rolesPageID) continue;
                if($template->name == 'permission' && $parent->id != $this->wire('config')->permissionsPageID) continue;
            }

            if(count($template->parentTemplates) && $parent->id && !in_array($parent->template->id, $template->parentTemplates)) {
                // this template specifies it can only be used with certain parents, and our parent's template isn't one of them
                continue;
            }

            if($parent->id && count($parent->template->childTemplates)) {
                // the page's parent only allows certain templates for it's children
                // if this isn't one of them, then continue;
                if(!in_array($template->id, $parent->template->childTemplates)) continue;
            }

            if($isSuperuser) {
                $templates[$template->id] = $template;

            } else if($template->noParents) {
                // user can't change to a template that has been specified as no more instances allowed
                // except for superuser... we'll let them do it
                continue;

            } else if((!$template->useRoles && $parentEditable) || $user->hasPermission('page-edit', $template)) {
                // determine if the template's assigned roles match up with the users's roles
                // and that at least one of those roles has page-edit permission
                if($user->hasPermission('page-create', $page)) {
                    // user is allowed to create more pages of this type, so template may be used
                    $templates[$template->id] = $template;
                }
            }
        }

        $this->allowedTemplatesEdit = $templates;
        return $templates;
    }

    /**
     * Is the given template or template ID allowed here?
     *
     */
    protected function isAllowedTemplateEdit($id, $cp) {

        // if the template is the same one already in place, of course it's allowed
        if($id == $cp->template->id) return true;

        // if we've made it this far, then get a list of templates that are allowed...
        $templates = $this->getAllowedTemplatesEdit($cp);

        // ...and determine if the supplied template is in that list
        return isset($templates[$id]);
    }


    /**
     * Returns an array of templates that are allowed to be used here
     *
     */
    protected function getAllowedTemplatesAdd($parent) {
        if(is_array($this->allowedTemplatesAdd)) return $this->allowedTemplatesAdd;
        $user = $this->wire('user');
        $templates = array();
        $allTemplates = count($this->predefinedTemplates) ? $this->predefinedTemplates : $this->wire('templates');
        $allParents = $this->getAllowedParentsAdd(null, $parent);
        $usersPageIDs = $this->wire('config')->usersPageIDs;
        $userTemplateIDs = $this->wire('config')->userTemplateIDs;

        // Doing the temporary unpublish was resulting in the published timestamp being updated every time
        // a page is saved. So replace with a one off check for now, because I don't think it's needed in this scenario.
        // Hopefully no side-effects.
        $parentEditable = $parent->editable();
        /*
        if($parent->hasStatus(Page::statusUnpublished)) {
            $parentEditable = $parent->editable();
        } else {
            // temporarily put the parent in an unpublished status so that we can check it from
            // the proper context: when page-publish permission exists, a page not not editable
            // if a user doesn't have page-publish permission to it, even though it may still
            // be editable if it was unpublished.
            $parent->setTrackChanges(false);
            $parent->addStatus(Page::statusUnpublished);
            $parentEditable = $parent->editable();
            $parent->removeStatus(Page::statusUnpublished);
            $parent->setTrackChanges(true);
        }
        */

        foreach($allTemplates as $t) {

            if($t->noParents) continue;
            if($t->useRoles && !$user->hasPermission('page-create', $t)) continue;
            if(!$t->useRoles && !$parentEditable) continue;
            if(!$t->useRoles && !$user->hasPermission('page-create', $parent)) continue;

            if(count($allParents) == 1) {
                if(count($parent->template->childTemplates)) {
                    // check that this template is allowed by the defined parent
                    if(!in_array($t->id, $parent->template->childTemplates)) continue;
                }
            }

            if(count($t->parentTemplates)) {
                // this template is only allowed for certain parents
                $allow = false;
                foreach($allParents as $parent) {
                    if(in_array($parent->template->id, $t->parentTemplates)) {
                        $allow = true;
                        break;
                    }
                }
                if(!$allow) continue;
            }

            if(in_array($t->id, $userTemplateIDs)) {
                // this is a user template: allow any parents defined in $config->usersPageIDs
                $allow = false;
                foreach($allParents as $parent) {
                    if(in_array($parent->id, $usersPageIDs)) {
                        $allow = true;
                        break;
                    }
                }
                if(!$allow) continue;

            } else if($t->name == 'role' && $parent->id != $this->wire('config')->rolesPageID) {
                // only allow role templates below rolesPageID
                continue;

            } else if($t->name == 'permission' && $parent->id != $this->wire('config')->permissionsPageID) {
                // only allow permission templates below permissionsPageID
                continue;
            }

            $templates[$t->id] = $t;
        }

        if($this->template || count($this->predefinedTemplates)) {
            $predefinedTemplates = count($this->predefinedTemplates) ? $this->predefinedTemplates : array($this->template);
            foreach($predefinedTemplates as $t) {
                $isUserTemplate = in_array($t->id, $userTemplateIDs);
                if($isUserTemplate && !isset($templates[$t->id]) && $user->hasPermission('user-admin')) {
                    // account for the unique situation of user-admin permission
                    // where all user-based templates are allowed
                    $templates[$t->id] = $t;
                }
            }
        }

        $this->allowedTemplatesAdd = $templates;

        return $templates;
    }


    /**
     * Get allowed parents
     *
     * This will always be 1-parent, unless predefinedParents was populated.
     *
     * @param Template $template Optionally specify a template to filter parents by
     * @return PageArray
     *
     */
    protected function getAllowedParentsAdd(Template $template = null, $pp) {
        if(count($this->predefinedParents)) {
            $parents = $this->predefinedParents;
        } else {
            $parents = new PageArray();
            if($pp) $parents->add($pp);
        }
        foreach($parents as $parent) {
            if(!$parent->addable()) $parents->remove($parent);
            if($parent->template->noChildren) $parents->remove($parent);
            if($template && count($parent->template->childTemplates)) {
                // parent only allows certain templates for children
                // if a template was given in the arguments, check that it is allowed
                if(!in_array($template->id, $parent->template->childTemplates)) {
                    $parents->remove($parent);
                }
            }
        }
        if($template && count($template->parentTemplates)) {
            // given template only allows certain parents
            foreach($parents as $parent) {
                if(!in_array($parent->template->id, $template->parentTemplates)) {
                    $parents->remove($parent);
                }
            }
        }
        return $parents;
    }


    /**
     * Is the given template or template ID allowed here?
     *
     * @param Template|int Template ID or object
     * @param Page $parent Optionally parent page to filter by
     * @return bool
     * @throws WireException of template argument can't be resolved
     *
     */
    protected function isAllowedTemplateAdd($template, Page $parent = null) {
        if(!is_object($template)) $template = $this->wire('templates')->get($template);
        if(!$template) throw new WireException('Unknown template');
        $templates = $this->getAllowedTemplatesAdd($parent);
        $allowed = isset($templates[$template->id]);
        if($allowed && $parent) {
            if(count($parent->template->childTemplates) && !in_array($template->id, $parent->template->childTemplates)) {
                $allowed = false;
            } else if($parent->template->noChildren) {
                $allowed = false;
            } else if(count($template->parentTemplates) && !in_array($parent->template->id, $template->parentTemplates)) {
                $allowed = false;
            } else if($template->noParents) {
                $allowed = false;
            }
        }
        return $allowed;
    }

    protected function newPage($parent, $template) {
        $np = new Page();
        $np->parent = $parent;
        $np->template = $template;
        if($this->wire('languages') && $this->wire('input')->post->activateOtherLanguages) {
            foreach($this->wire('languages')->find('name!=default') as $language) $np->set("status$language", 1);
        }
        return $np;
    }

    public function removeChildrenTab ($event) {

        $form = $event->return;

        $fieldset = $form->find("id=ProcessPageEditChildren")->first();

        if(!is_object($fieldset)) return;

        $form->remove($fieldset);
        $event->object->removeTab("ProcessPageEditChildren");
    }




    function buildCoreSettings ($data, $wrapper, $pid = null) {

        $fieldset = $this->wire('modules')->get("InputfieldFieldset");
        $fieldset->attr('id', 'batch_child_editor_mode_settings_fieldset');
        $fieldset->label = __("Mode Settings");
        $fieldset->collapsed = Inputfield::collapsedYes;
        $wrapper->add($fieldset);

        $f = $this->wire('modules')->get("InputfieldCheckboxes");
        $f->attr('name', 'editModes');
        $f->label = __('Modes');
        $f->required = true;
        $f->columnWidth = 25;
        $f->description = __('Which modes you want available. If more than one checked, then the user can decide.');
        if(defined('Inputfield::collapsedYesAjax') || defined('\ProcessWire\Inputfield::collapsedYesAjax')) $f->addOption("lister", __("Lister"));
        $f->addOption("edit", __("Edit"));
        $f->addOption("add", __("Add"));
        $f->addOption("update", __("Update"));
        $f->addOption("replace", __("Replace"));
        $f->addOption("export", __("Export CSV"));
        $f->value = $pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['editModes'] : $data['editModes'];
        $f->notes = __("The Edit and Add modes are safer than the Update and Replace modes, because they are non-destructive.\n\nUpdate will edit the content of fields of existing pages. Replace will delete all child pages and create new ones.");
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldRadios");
        $f->attr('name', 'defaultMode');
        $f->label = __('Default Mode');
        $f->columnWidth = 25;
        $f->showIf = "editModes.count>1";
        $f->description = __('If more than one mode available to user, which one should be open by default.');
        $f->addOption("", __("None"));
        if(defined('Inputfield::collapsedYesAjax') || defined('\ProcessWire\Inputfield::collapsedYesAjax')) $f->addOption("lister", __("Lister"));
        $f->addOption("edit", __("Edit"));
        $f->addOption("add", __("Add"));
        $f->addOption("update", __("Update"));
        $f->addOption("replace", __("Replace"));
        $f->addOption("export", __("Export CSV"));
        $f->value = $pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['defaultMode'] : $data['defaultMode'];
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldCheckbox");
        $f->attr('name', 'overwriteNames');
        $f->label = __('Overwrite names');
        $f->showIf = "editModes=edit|update";
        $f->columnWidth = 25;
        $f->description = __('Whether to overwrite the name of the page, and not just the title.');
        $f->attr('checked', ($pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['overwriteNames'] : $data['overwriteNames']) ? 'checked' : '' );
        $f->notes = __("Only relevant for Edit and Update modes. This option can cause problems if the affected child pages are part of the front end structure of the site. It may result in broken links, etc.");
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldCheckbox");
        $f->attr('name', 'allowOverrideOverwriteNames');
        $f->label = __('Allow user to change "Overwrite Names" setting');
        $f->showIf = "editModes=edit|update";
        $f->columnWidth = 25;
        $f->description = __('Whether an admin user can change the override option when doing a batch edit.');
        $f->attr('checked', ($pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['allowOverrideOverwriteNames'] : $data['allowOverrideOverwriteNames']) ? 'checked' : '' );
        $f->notes = __("Only relevant for Edit and Update modes.");
        $fieldset->add($f);

        $fieldset = $this->wire('modules')->get("InputfieldFieldset");
        $fieldset->attr('id', 'batch_child_editor_data_protection_fieldset');
        $fieldset->label = __("Content / Deletion / Protection");
        //$fieldset->showIf = "editModes=edit|add|update|replace";
        $fieldset->collapsed = Inputfield::collapsedYes;
        $wrapper->add($fieldset);

        //only for page context settings, NOT module config settings
        if($this->wire('input')->get->id) {
            $f = $this->wire('modules')->get("InputfieldPageListSelect");
            $f->attr('name', 'parentPage');
            $f->label = __('Parent Page');
            $f->columnWidth = 100;
            $f->description = __('If selected, the editable children will be from this parent instead.');
            $f->value = $pid && isset($data['pageSettings'][$pid]) && isset($data['pageSettings'][$pid]['parentPage']) ? $data['pageSettings'][$pid]['parentPage'] : '';
            $fieldset->add($f);
        }

        $f = $this->wire('modules')->get("InputfieldCheckbox");
        $f->attr('name', 'disableContentProtection');
        $f->label = __('Disable content protection');
        $f->showIf = "editModes=replace|edit";
        $f->columnWidth = 25;
        $f->description = __('If checked, replace and edit modes will destructively delete children if they have field content and/or their own children. This is not different to normal ProcessWire behavior, but with this module it is very easy to delete important content, hence this check.');
        $f->notes = __("This can be extremely destructive, use with extreme caution!");
        $f->attr('checked', ($pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['disableContentProtection'] : $data['disableContentProtection']) ? 'checked' : '' );
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldCheckbox");
        $f->attr('name', 'allowTemplateChanges');
        $f->label = __('Allow template changes');
        $f->showIf = "editModes=edit";
        $f->columnWidth = 25;
        $f->description = __('If checked, edit mode will allow template changes (if user has the "page-template" permission). There are no warnings about data loss on template change, so be careful with this.');
        $f->notes = __("This can be extremely destructive, use with extreme caution!");
        $f->attr('checked', ($pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['allowTemplateChanges'] : $data['allowTemplateChanges']) ? 'checked' : '' );
        $fieldset->add($f);

        //only for module config settings, NOT page context settings
        if(!$this->wire('input')->get->id) {
            $f = $this->wire('modules')->get("InputfieldCheckbox");
            $f->attr('name', 'allowAdminPages');
            $f->label = __('Allow editing admin pages');
            $f->showIf = "editModes=edit";
            $f->columnWidth = 25;
            $f->description = __('If checked, this module will work on Admin pages.');
            $f->notes = __("This can be extremely destructive, use with extreme caution - it has the potential to break the entire PW install!");
            $f->attr('checked', $data['allowAdminPages'] ? 'checked' : '' );
            $fieldset->add($f);
        }

        $f = $this->wire('modules')->get("InputfieldRadios");
        $f->attr('name', 'trashOrDelete');
        $f->label = __('Trash or Delete');
        $f->columnWidth = 25;
        $f->description = __('Whether you want deleted pages moved to the trash or permanently deleted.');
        $f->addOption("trash", __("Trash"));
        $f->addOption("delete", __("Delete"));
        $f->value = $pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['trashOrDelete'] : $data['trashOrDelete'];
        $fieldset->add($f);

        $fieldset = $this->wire('modules')->get("InputfieldFieldset");
        $fieldset->attr('id', 'batch_child_editor_lister_options_fieldset');
        $fieldset->label = __("Lister mode settings");
        $fieldset->showIf = "editModes=lister";
        $fieldset->collapsed = Inputfield::collapsedYes;
        $wrapper->add($fieldset);


        $f = $this->wire('modules')->get('InputfieldSelect');
        $f->attr('name', 'listerDefaultSort');
        $f->label = __('Default Sort Order');
        $options = array();
        $sortColumns = array('id', 'name', 'title', 'modified', 'created', 'sort', 'template');
        foreach($sortColumns as $name) {
            $label = $name;
            $options[$name] = $label;
        }
        $f->addOption(__('Ascending'), $options);
        $labels = $options;
        foreach($options as $name => $label) {
            $options["-$name"] = $label;
            unset($options[$name]);
        }
        $f->addOption(__('Descending'), $options);
        $f->value = $pid && isset($data['pageSettings'][$pid]) && isset($data['pageSettings'][$pid]['listerDefaultSort']) ? $data['pageSettings'][$pid]['listerDefaultSort'] : $data['listerDefaultSort'];
        $f->icon = 'sort-alpha-asc';
        $fieldset->add($f);


        $f = $this->wire('modules')->get("InputfieldAsmSelect");
        $f->attr('name', 'listerColumns');
        $f->label = __('Default Columns');
        $f->showIf = "editModes=lister";
        $f->description = __('Which columns displayed by default.');
        $f->addOption('title', __("Title"));
        foreach($data['systemFields'] as $systemField => $systemFieldLabel) {
            $f->addOption($systemField, $systemFieldLabel);
        }
        //get the right language label
        $lang = $this->wire('user')->language;
        $label = 'label';
        if ($this->wire('modules')->isInstalled("LanguageSupport") && $this->wire('user')->language->title != 'default') {
            $label = "label{$lang}";
        }
        foreach($this->wire('fields') as $field) {
            $f->addOption($field->name, $field->$label ? $field->$label : $field->name);
        }
        $f->value = $pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['listerColumns'] : $data['listerColumns'];
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldSelector");
        $f->attr('name', 'defaultFilter');
        $f->label = __('Default Filter');
        $f->showIf = "editModes=lister";
        $f->description = __('Which filters will be applied by default.');
        $f->notes = __('These filters will be in addition to limiting the results to the child of the current page.');
        $f->value = $pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['defaultFilter'] : $data['defaultFilter'];
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldCheckbox");
        $f->attr('name', 'listerConfigurable');
        $f->label = __('Allow Lister Configuration');
        $f->showIf = "editModes=lister";
        $f->description = __('If checked, the user will be able to configure the Filters, Columns, and use Actions.');
        $f->attr('checked', ($pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['listerConfigurable'] : $data['listerConfigurable']) ? 'checked' : '' );
        $fieldset->add($f);

        $fieldset = $this->wire('modules')->get("InputfieldFieldset");
        $fieldset->attr('id', 'batch_child_editor_data_entry_fieldset');
        $fieldset->label = __("Data entry / CSV import settings");
        $fieldset->showIf = "editModes=add|update|replace";
        $fieldset->collapsed = Inputfield::collapsedYes;
        $wrapper->add($fieldset);

        $f = $this->wire('modules')->get("InputfieldCheckboxes");
        $f->attr('name', 'csvOptions');
        $f->label = __('Page data options');
        $f->showIf = "editModes=add|update|replace";
        $f->required = true;
        $f->requiredIf = "editModes=add|update|replace";
        $f->columnWidth = $this->wire('input')->get->id ? 25 : 50;
        $f->description = __('Which page entry options do you want available.');
        $f->notes = __("For creating page title/names only, then Text / Paste is likely the most relevant option. CSV options to the right only relevant if populating other page fields in addition to the title/name.");
        $f->addOption("paste", __("Text / Paste"));
        $f->addOption("link", __("Link (URL)"));
        $f->addOption("upload", __("Upload"));
        $f->value = $pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['csvOptions'] : $data['csvOptions'];
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldCheckboxes");
        $f->attr('name', 'csvOptionsCollapsed');
        $f->label = __('Page data options collapsed status');
        $f->showIf = "editModes=add|update|replace";
        $f->columnWidth = $this->wire('input')->get->id ? 25 : 50;
        $f->description = __('Which page entry options do you want open vs collapsed.');
        $f->notes = __("Checked options will be open by default.");
        $f->addOption("paste", __("Text / Paste"));
        $f->addOption("link", __("Link (URL)"));
        $f->addOption("upload", __("Upload"));
        $f->value = $pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['csvOptionsCollapsed'] : $data['csvOptionsCollapsed'];
        $fieldset->add($f);

        //only for page context settings, NOT module config settings
        if($this->wire('input')->get->id) {
            $f = $this->wire('modules')->get("InputfieldTextarea");
            $f->name = 'fieldPairings';
            $f->label = __('CSV field pairings');
            $f->description = __("Enter CSV column number and Processwire field name pairs to match up when importing. If empty, all CSV columns will be imported in order and therefore must match the number of fields in the template for this page.");
            $f->notes = __("Column numbers start at 1 so in this example we are excluding the 3rd column in the CSV file:\n1:title\n2:body\n4:notes\n5:parentfield.subfield\n\nUnless the only modes are \"Edit\" and \"Update\", you must include at least the \"title\" field, but all other fields are optional.");
            $f->showIf = "editModes=add|update|replace";
            $f->columnWidth = 50;
            $f->value = isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['fieldPairings'] : '';
            $fieldset->add($f);

            $f = $this->wire('modules')->get("InputfieldCheckbox");
            $f->attr('name', 'matchByFirstField');
            $f->label = __('Match children by first field pairing field');
            $f->showIf = "editModes=update";
            $f->columnWidth = 33;
            $f->description = __("If checked, child pages will be matched by the content of the first field pairing field, not the order of rows in the CSV file. If checked, the titles of existing child pages won't be shown in the Text/Paste input field.");
            $f->notes = __("Only relevant in Update mode. This is useful if you want to update only certain child pages. Note that you must have the field defined in the field pairings setting.");
            $f->attr('checked', ($pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['matchByFirstField'] : $data['matchByFirstField']) ? 'checked' : '' );
            $fieldset->add($f);

            $f = $this->wire('modules')->get("InputfieldCheckbox");
            $f->attr('name', 'noCaseUpdate');
            $f->label = __("Don't update case of matched field");
            $f->showIf = "editModes=update, matchByFirstField=1";
            $f->columnWidth = 34;
            $f->description = __('If checked, and the matched field was a case-insensitive match, do not update this field.');
            $f->notes = __("Only relevant in Update mode and if 'Match children by first field pairing field' is checked.");
            $f->attr('checked', ($pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['noCaseUpdate'] : $data['noCaseUpdate']) ? 'checked' : '' );
            $fieldset->add($f);

            $f = $this->wire('modules')->get("InputfieldCheckbox");
            $f->attr('name', 'addNewNoMatch');
            $f->label = __('Add new child page if no match');
            $f->showIf = "editModes=update, matchByFirstField=1";
            $f->columnWidth = 33;
            $f->description = __('If checked, a new child page will be created if the match by field returns no matching child page.');
            $f->notes = __("Only relevant in Update mode and if 'Match children by first field pairing field' is checked.");
            $f->attr('checked', ($pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['addNewNoMatch'] : $data['addNewNoMatch']) ? 'checked' : '' );
            $fieldset->add($f);

        }

        $f = $this->wire('modules')->get("InputfieldText");
        $f->name = 'csvImportFieldSeparator';
        $f->label = __('CSV fields separated with');
        $f->showIf = "editModes=add|update|replace";
        $f->notes = __('For tab separated, enter: tab');
        $f->value = $pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['csvImportFieldSeparator'] : $data['csvImportFieldSeparator'];
        $f->columnWidth = 25;
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldText");
        $f->name = 'csvImportFieldEnclosure';
        $f->label = __('CSV field enclosure');
        $f->showIf = "editModes=add|update|replace";
        $f->value = $pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['csvImportFieldEnclosure'] : $data['csvImportFieldEnclosure'];
        $f->columnWidth = 25;
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldCheckbox");
        $f->attr('name', 'ignoreFirstRow');
        $f->label = __('CSV ignore the first row');
        $f->description = __('Use this if the first row contains column/field labels.');
        $f->showIf = "editModes=add|update|replace";
        $f->attr('checked', ($pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['ignoreFirstRow'] : $data['ignoreFirstRow']) ? 'checked' : '' );
        $f->columnWidth = 25;
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldText");
        $f->attr('name', 'importMultipleValuesSeparator');
        $f->label = __('Multiple values separator');
        $f->description = __('Separator for multiple values like Page fields, etc.');
        $f->notes = __('Default is | Other useful options include \r for new lines.');
        $f->showIf = "editModes=add|update|replace";
        $f->value = $pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['importMultipleValuesSeparator'] : $data['importMultipleValuesSeparator'];
        $f->columnWidth = 25;
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldCheckbox");
        $f->attr('name', 'newImageFirst');
        $f->label = __('New Image First');
        $f->description = __('If new image added, add it in first place, rather than last.');
        $f->notes = __("Only relevant in Update mode.");
        $f->showIf = "editModes=update";
        $f->attr('checked', ($pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['newImageFirst'] : $data['newImageFirst']) ? 'checked' : '' );
        $f->columnWidth = 100;
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldCheckbox");
        $f->attr('name', 'allowOverrideCsvImportSettings');
        $f->label = __('User override CSV settings');
        $f->description = __('Allow user to override CSV separator, enclosure, and ignore first row settings.');
        $f->showIf = "editModes=add|update|replace";
        $f->attr('checked', ($pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['allowOverrideCsvImportSettings'] : $data['allowOverrideCsvImportSettings']) ? 'checked' : '' );
        $f->columnWidth = 100;
        $fieldset->add($f);

        $fieldset = $this->wire('modules')->get("InputfieldFieldset");
        $fieldset->attr('id', 'batch_child_editor_csv_export_fieldset');
        $fieldset->label = __('CSV export settings');
        $fieldset->description = __('If you want to specify the fields to export, you need to define them separately for each parent page by setting the Configurable pages first. All these settings can also be overriden on a page specific basis.');
        $fieldset->showIf = "editModes=export";
        $fieldset->collapsed = Inputfield::collapsedYes;
        $wrapper->add($fieldset);

        //only for page context settings, NOT module config settings
        if($this->wire('input')->get->id) {

            $f = $this->wire('modules')->get("InputfieldSelector");
            $f->attr('name', 'pagesToInclude');
            $f->label = __('Pages to Include');
            $f->description = __('Leave blank to automatically select all child pages&nbsp;(not hidden and published)');
            $f->initValue = "parent={$pid}";
            $f->value = $pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['pagesToInclude'] : $data['pagesToInclude'];
            $f->collapsed = Inputfield::collapsedBlank;
            $fieldset->add($f);

            $f = $this->wire('modules')->get("InputfieldAsmSelect");
            $f->name = "exportFields";
            $f->showIf = "editModes=export";
            $f->label = __('Fields to export');
            $f->description = __('Choose and sort the fields to include in the CSV export');

            //system fields
            foreach($data['systemFields'] as $systemField => $systemFieldLabel) {
                $f->addOption($systemField, $systemFieldLabel);
            }
            $allFields = array();
            $pp = isset($data['pageSettings'][$pid]['parentPage']) && $data['pageSettings'][$pid]['parentPage'] ? $this->wire('pages')->get($data['pageSettings'][$pid]['parentPage']) : $this->wire('pages')->get((int)$this->wire('input')->get->id);
            foreach($pp->children("include=all") as $child) {
                foreach($child->fields as $cf) {
                    if(!in_array($cf, $allFields)) $allFields[] = $cf;
                }
            }

            //custom template fields for all child pages
            foreach($allFields as $pf) {
                $f->addOption($pf->name, $pf->label ? $pf->label : $pf->name);
                $f->value = $pf->name;
            }
            $f->value = isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['exportFields'] : '';
            $fieldset->add($f);
        }

        $f = $this->wire('modules')->get("InputfieldText");
        $f->name = 'csvExportFieldSeparator';
        $f->label = __('CSV fields separated with');
        $f->showIf = "editModes=export";
        $f->notes = __('For tab separated, enter: tab');
        $f->value = $pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['csvExportFieldSeparator'] : $data['csvExportFieldSeparator'];
        $f->columnWidth = 33;
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldText");
        $f->name = 'csvExportFieldEnclosure';
        $f->label = __('CSV field enclosure');
        $f->showIf = "editModes=export";
        $f->value = $pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['csvExportFieldEnclosure'] : $data['csvExportFieldEnclosure'];
        $f->columnWidth = 34;
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldText");
        $f->name = 'csvExportExtension';
        $f->label = __('File extension');
        $f->showIf = "editModes=export";
        $f->value = $pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['csvExportExtension'] : $data['csvExportExtension'];
        $f->columnWidth = 33;
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldCheckbox");
        $f->attr('name', 'columnsFirstRow');
        $f->label = __('Column labels');
        $f->label2 = __('Put column names in the first row');
        $f->showIf = "editModes=export";
        $f->attr('checked', ($pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['columnsFirstRow'] : $data['columnsFirstRow']) ? 'checked' : '' );
        $f->columnWidth = 33;
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldText");
        $f->attr('name', 'exportMultipleValuesSeparator');
        $f->label = __('Multiple values separator');
        $f->description = __('Separator for multiple values like Page fields, files/images, multiplier, etc.');
        $f->notes = __('Default is | Other useful options include \r for new lines when importing into Excel.');
        $f->showIf = "editModes=export";
        $f->value = $pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['exportMultipleValuesSeparator'] : $data['exportMultipleValuesSeparator'];
        $f->columnWidth = 34;
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldCheckbox");
        $f->attr('name', 'formatExport');
        $f->label = __('Format Export');
        $f->label2 = __('Turns on output formatting for exported values.');
        $f->notes = __('If you will be importing this back into ProcessWire, you should leave unchecked.');
        $f->showIf = "editModes=export";
        $f->attr('checked', ($pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['formatExport'] : $data['formatExport']) ? 'checked' : '' );
        $f->columnWidth = 33;
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldCheckbox");
        $f->attr('name', 'allowOverrideCsvExportSettings');
        $f->label = __('User override CSV settings');
        $f->description = __('Allow user to override Fields to export, CSV separator, enclosure, and column labels in first row settings.');
        $f->showIf = "editModes=export";
        $f->attr('checked', ($pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['allowOverrideCsvExportSettings'] : $data['allowOverrideCsvExportSettings']) ? 'checked' : '' );
        $fieldset->add($f);

        $fieldset = $this->wire('modules')->get("InputfieldFieldset");
        $fieldset->attr('id', 'batch_child_editor_position_fieldset');
        $fieldset->label = __("Editor position, open, and load settings");
        $fieldset->collapsed = Inputfield::collapsedYes;
        $wrapper->add($fieldset);

        $f = $this->wire('modules')->get("InputfieldRadios");
        $f->attr('name', 'position');
        $f->label = __('Position');
        $f->columnWidth = 14;
        $f->description = __('Where to position the Batch Child Editor tool. Replace will remove the existing page tree. New Tab will put this editor in a new tab. Inline Fieldset will add the editor to the Content tab.');
        $f->addOption("top", __("Top"));
        $f->addOption("bottom", __("Bottom"));
        $f->addOption("replace", __("Replace"));
        $f->addOption("newTab", __("New Tab"));
        $f->addOption("inlineFieldset", __("Inline Fieldset"));
        $f->value = $pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['position'] : $data['position'];
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldSelect");
        $f->attr('name', 'insertAfterField');
        $f->showIf = "position=inlineFieldset";
        $f->requiredIf = "position=inlineFieldset";
        $f->label = __('Insert After');
        $f->columnWidth = 14;
        $f->description = __('Choose the field you want to insert the edit fieldset after.');
        $f->notes = __('If none selected, it will be appended to the end of the Content tab.');
        foreach($this->wire('fields') as $field) {
            $f->addOption($this->wire('fields')->get($field)->id, $this->wire('fields')->get($field)->name);
        }
        $f->value = $pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['insertAfterField'] : $data['insertAfterField'];
        $fieldset->add($f);

        //only for page context settings, NOT module config settings
        if($this->wire('input')->get->id) {
            $f = $this->wire('modules')->get("InputfieldCheckbox");
            $f->attr('name', 'hideChildren');
            $f->label = __('Hide Children');
            $f->columnWidth = 14;
            $f->description = __('If checked, all children will be removed from the page tree.');
            $f->notes = __('Use this option if you want to force editing of child pages through BCE.');
            $f->attr('checked', ($pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['hideChildren'] : $data['hideChildren']) ? 'checked' : '' );
            $fieldset->add($f);
        }

        $f = $this->wire('modules')->get("InputfieldCheckbox");
        $f->attr('name', 'removeChildrenTab');
        $f->showIf = "position=inlineFieldset|newTab";
        $f->label = __('Remove Children Tab');
        $f->columnWidth = 14;
        $f->description = __('If checked, the Children tab will be removed.');
        $f->notes = __('This option to help to simplify the interface when the position is Inline Fieldset or New Tab');
        $f->attr('checked', ($pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['removeChildrenTab'] : $data['removeChildrenTab']) ? 'checked' : '' );
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldCheckbox");
        $f->attr('name', 'loadOpen');
        $f->label = __('Load open, not collapsed');
        $f->showIf = "position!=newTab";
        $f->columnWidth = 14;
        $f->description = __('If checked, batch child editor will initially be open, not collapsed.');
        $f->attr('checked', ($pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['loadOpen'] : $data['loadOpen']) ? 'checked' : '' );
        $fieldset->add($f);

        if(defined('Inputfield::collapsedYesAjax') || defined('\ProcessWire\Inputfield::collapsedYesAjax')) {
            $f = $this->wire('modules')->get("InputfieldRadios");
            $f->attr('name', 'openMethod');
            $f->label = __('Open Method');
            //$f->showIf = "loadOpen=0";
            $f->columnWidth = 14;
            $f->description = __('If initially collapsed, how should it be opened?');
            $f->notes = __('AJAX can be useful to improve performance of the page editor for page trees with a lot of children. Especially useful if you are using the "New Tab" position option.');
            $f->addOption("normal", __("Normal"));
            $f->addOption("ajax", __("AJAX"));
            $f->value = $pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['openMethod'] : $data['openMethod'];
            $fieldset->add($f);
        }

        $f = $this->wire('modules')->get("InputfieldText");
        $f->attr('name', 'tabName');
        $f->label = __('Tab / fieldset name');
        $f->required = 1;
        $f->columnWidth = 16;
        $f->description = __('Name for the tab / fieldset.');
        $f->value = $pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['tabName'] : $data['tabName'];
        $fieldset->add($f);

        $fieldset = $this->wire('modules')->get("InputfieldFieldset");
        $fieldset->attr('id', 'batch_child_editor_custom_text_fieldset');
        $fieldset->label = __("Custom labels, descriptions and notes");
        $fieldset->collapsed = Inputfield::collapsedYes;
        $wrapper->add($fieldset);

        $f = $this->wire('modules')->get("InputfieldText");
        $f->attr('name', 'editModeTitle');
        $f->label = __('Edit mode title');
        $f->showIf = "editModes=edit";
        $f->columnWidth = 33;
        $f->description = __('Custom title in Edit mode.');
        $f->value = $pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['editModeTitle'] : $data['editModeTitle'];
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldTextarea");
        $f->attr('name', 'editModeDescription');
        $f->label = __('Edit mode description');
        $f->showIf = "editModes=edit";
        $f->columnWidth = 34;
        $f->description = __('Custom text for description in Edit mode.');
        $f->value = $pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['editModeDescription'] : $data['editModeDescription'];
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldTextarea");
        $f->attr('name', 'editModeNotes');
        $f->label = __('Edit mode notes');
        $f->showIf = "editModes=edit";
        $f->columnWidth = 33;
        $f->description = __('Custom text for notes in Edit mode.');
        $f->value = $pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['editModeNotes'] : $data['editModeNotes'];
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldText");
        $f->attr('name', 'listerModeTitle');
        $f->label = __('Lister mode title');
        $f->showIf = "editModes=lister";
        $f->columnWidth = 33;
        $f->description = __('Custom title in Lister mode.');
        $f->value = $pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['listerModeTitle'] : $data['listerModeTitle'];
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldTextarea");
        $f->attr('name', 'listerModeDescription');
        $f->label = __('Lister mode description');
        $f->showIf = "editModes=lister";
        $f->columnWidth = 34;
        $f->description = __('Custom text for description in Lister mode.');
        $f->value = $pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['listerModeDescription'] : $data['listerModeDescription'];
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldTextarea");
        $f->attr('name', 'listerModeNotes');
        $f->label = __('Lister mode notes');
        $f->showIf = "editModes=lister";
        $f->columnWidth = 33;
        $f->description = __('Custom text for notes in Lister mode.');
        $f->value = $pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['listerModeNotes'] : $data['listerModeNotes'];
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldText");
        $f->attr('name', 'addModeTitle');
        $f->label = __('Add mode title');
        $f->showIf = "editModes=add";
        $f->columnWidth = 33;
        $f->description = __('Custom title for Add mode.');
        $f->value = $pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['addModeTitle'] : $data['addModeTitle'];
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldTextarea");
        $f->attr('name', 'addModeDescription');
        $f->label = __('Add mode description');
        $f->showIf = "editModes=add";
        $f->columnWidth = 34;
        $f->description = __('Custom text for description in Add mode.');
        $f->value = $pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['addModeDescription'] : $data['addModeDescription'];
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldTextarea");
        $f->attr('name', 'addModeNotes');
        $f->label = __('Add mode notes');
        $f->showIf = "editModes=add";
        $f->columnWidth = 33;
        $f->description = __('Custom text for notes in Add mode.');
        $f->value = $pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['addModeNotes'] : $data['addModeNotes'];
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldText");
        $f->attr('name', 'updateModeTitle');
        $f->label = __('Update mode title');
        $f->showIf = "editModes=update";
        $f->columnWidth = 33;
        $f->description = __('Custom title for Update mode.');
        $f->value = $pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['updateModeTitle'] : $data['updateModeTitle'];
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldTextarea");
        $f->attr('name', 'updateModeDescription');
        $f->label = __('Update mode description');
        $f->showIf = "editModes=update";
        $f->columnWidth = 34;
        $f->description = __('Custom text for description in Update mode.');
        $f->value = $pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['updateModeDescription'] : $data['updateModeDescription'];
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldTextarea");
        $f->attr('name', 'updateModeNotes');
        $f->label = __('Update mode notes');
        $f->showIf = "editModes=update";
        $f->columnWidth = 33;
        $f->description = __('Custom text for notes in Update mode.');
        $f->value = $pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['updateModeNotes'] : $data['updateModeNotes'];
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldText");
        $f->attr('name', 'replaceModeTitle');
        $f->label = __('Replace mode title');
        $f->showIf = "editModes=replace";
        $f->columnWidth = 33;
        $f->description = __('Custom title for Replace mode.');
        $f->value = $pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['replaceModeTitle'] : $data['replaceModeTitle'];
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldTextarea");
        $f->attr('name', 'replaceModeDescription');
        $f->label = __('Replace mode description');
        $f->showIf = "editModes=replace";
        $f->columnWidth = 34;
        $f->description = __('Custom text for description in Replace mode.');
        $f->value = $pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['replaceModeDescription'] : $data['replaceModeDescription'];
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldTextarea");
        $f->attr('name', 'replaceModeNotes');
        $f->label = __('Replace mode notes');
        $f->showIf = "editModes=replace";
        $f->columnWidth = 33;
        $f->description = __('Custom text for notes in Replace mode.');
        $f->value = $pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['replaceModeNotes'] : $data['replaceModeNotes'];
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldText");
        $f->attr('name', 'exportModeTitle');
        $f->label = __('Export mode title');
        $f->showIf = "editModes=export";
        $f->columnWidth = 33;
        $f->description = __('Custom title for Export mode.');
        $f->value = $pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['exportModeTitle'] : $data['exportModeTitle'];
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldTextarea");
        $f->attr('name', 'exportModeDescription');
        $f->label = __('Export mode description');
        $f->showIf = "editModes=export";
        $f->columnWidth = 34;
        $f->description = __('Custom text for description in Export mode.');
        $f->value = $pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['exportModeDescription'] : $data['exportModeDescription'];
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldTextarea");
        $f->attr('name', 'exportModeNotes');
        $f->label = __('Export mode notes');
        $f->showIf = "editModes=export";
        $f->columnWidth = 33;
        $f->description = __('Custom text for notes in Export mode.');
        $f->value = $pid && isset($data['pageSettings'][$pid]) ? $data['pageSettings'][$pid]['exportModeNotes'] : $data['exportModeNotes'];
        $fieldset->add($f);

    }

}
