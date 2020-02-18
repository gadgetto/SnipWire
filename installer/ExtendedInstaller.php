<?php
namespace SnipWire\Installer;

/**
 * Extended resources installer for SnipWire.
 * (This file is part of the SnipWire package)
 * 
 * Licensed under MPL 2.0 (see LICENSE file provided with this package)
 * Copyright 2019 by Martin Gartner
 *
 * ProcessWire 3.x, Copyright 2019 by Ryan Cramer
 * https://processwire.com
 *
 */

use ProcessWire\Field;
use ProcessWire\Fieldgroup;
use ProcessWire\Page;
use ProcessWire\Permission;
use ProcessWire\Template;
use ProcessWire\Wire;
use ProcessWire\WireException;

class ExtendedInstaller extends Wire {

    const installerResourcesDirName = 'resources';
    
    const installerModeConfig = 1;
    const installerModeTemplates = 2;
    const installerModeFields = 4;
    const installerModePages = 8;
    const installerModePermissions = 16;
    const installerModeFiles = 32;
    const installerModeAll = 63;

    /**var string $snipWireRootUrl The root URL to ProcessSnipWire page */
    protected $snipWireRootUrl = '';

    /** @var string $resourcesFile Name of file which holds installer resources */
    protected $resourcesFile = '';
    
    /** @var array $resources Installation resources */
    protected $resources = array();
    
    /**
     * Constructor for ExtendedInstaller class.
     * 
     * @return void
     * 
     */
    public function __construct() {
        $this->snipWireRootUrl = rtrim($this->wire('pages')->findOne('template=admin, name=snipwire')->url, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        parent::__construct();
    }

    /**
     * Retrieve extended installation resources from file.
     * (file needs to be in same folder as this class file)
     * 
     * @param string $fileName The name of the resources file
     * @throws WireException
     * 
     */
    public function setResourcesFile($fileName) {
        $path = dirname(__FILE__) . DIRECTORY_SEPARATOR . self::installerResourcesDirName . DIRECTORY_SEPARATOR . $fileName;
        if (file_exists($path)) {
            include $path;
            if (!is_array($resources) || !count($resources)) {
                $message = sprintf($this->_('Installation aborted. Invalid resources array in file [%s].'), $resources);
                throw new WireException($message);
            }
        } else  {
            $message = sprintf($this->_('Installation aborted. File [%s] not found.'), $resources);
            throw new WireException($message);
        }
        $this->resources = $resources;
    }

    /**
     * Set installation resources from array.
     * (multidimensional array)
     * 
     * @param array $resources Installation resources array
     * @throws WireException
     * 
     */
    public function setResources($resources) {
        if (!is_array($resources) || !count($resources)) {
            $message = $this->_('Installation aborted. Invalid resources array.');
            throw new WireException($message);
        }
        $this->resources = $resources;
    }

    /**
     * Get install resources array.
     *
     * @return array
     *
     */
    public function getResources() {
        return $this->resources;
    }
    
    /**
     * Installer for extended resources.
     *
     * @param integer $mode
     * @return boolean true | false (if installations has errors)
     *
     */
    public function installResources($mode = self::installerModeAll) {        
        if (!$this->resources) {
            $message  =       $this->_('Installation aborted. No resources array provided.');
            $message .= ' ' . $this->_('Please use "setResourcesFile" or "setResources" method to provide a resources array.');
            throw new WireException($message);
        }
        
        //
        // Save module(s) config(s)
        //
        if (
            !empty($this->resources['config']) &&
            is_array($this->resources['config']) &&
            $mode & self::installerModeConfig
        ) {
            foreach ($this->resources['config'] as $item) {
                $this->_addModuleConfigData($item);
            }
        }

        //
        // Install templates
        //
        if (
            !empty($this->resources['templates']) &&
            is_array($this->resources['templates']) &&
            $mode & self::installerModeTemplates
        ) {
            foreach ($this->resources['templates'] as $item) {
                $this->_installTemplate($item);
            }
            // Solve template dependencies (after installing all templates!)
            foreach ($this->resources['templates'] as $item) {
                $this->_setTemplateDependencies($item);
            }
        }
        
        //
        // Install files
        //
        if (
            !empty($this->resources['files']) &&
            is_array($this->resources['files']) &&
            $mode & self::installerModeFiles
        ) {
            foreach ($this->resources['files'] as $file) {
                $this->_installFile($file);
            }
        }

        //
        // Install fields
        //
        if (
            !empty($this->resources['fields']) &&
            is_array($this->resources['fields']) &&
            $mode & self::installerModeFields
        ) {
            foreach ($this->resources['fields'] as $item) {
                $this->_installField($item);
            }
        }

        //
        // Install pages
        //
        if (
            !empty($this->resources['pages']) &&
            is_array($this->resources['pages']) &&
            $mode & self::installerModePages
        ) {
            foreach ($this->resources['pages'] as $item) {
                $this->_installPage($item);
            }
        }

        //
        // Install permissions
        //
        if (
            !empty($this->resources['permissions']) &&
            is_array($this->resources['permissions']) &&
            $mode & self::installerModePermissions
        ) {
            foreach ($this->resources['permissions'] as $item) {
                $this->_installPermission($item);
            }
        }
        
        return ($this->errors('array')) ? false : true;    
    }

    /**
     * Uninstaller for extended resources.
     *
     * @param integer $mode
     * @return boolean true | false (if uninstallation has errors)
     *
     */
    public function uninstallResources($mode = self::installerModeAll) {
        if (!$this->resources) {
            $message  =       $this->_('Uninstallation aborted. No resources array provided.');
            $message .= ' ' . $this->_('Please use "setResourcesFile" or "setResources" method to provide a resources array.');
            throw new WireException($message);
        }

        //
        // Uninstall pages
        //
        if (
            !empty($this->resources['pages']) &&
            is_array($this->resources['pages']) &&
            $mode & self::installerModePages
        ) {
            foreach ($this->resources['pages'] as $item) {
                $this->_uninstallPage($item);
            }
        }

        //
        // Uninstall fields
        //
        if (
            !empty($this->resources['fields']) &&
            is_array($this->resources['fields']) &&
            $mode & self::installerModeFields
        ) {
            foreach ($this->resources['fields'] as $item) {
                $this->_uninstallField($item);
            }
        }

        //
        // Uninstall files
        //
        if (
            !empty($this->resources['files']) &&
            is_array($this->resources['files']) &&
            $mode & self::installerModeFiles
        ) {
            foreach ($this->resources['files'] as $file) {
                $this->_uninstallFile($file);
            }
        }

        //
        // Uninstall templates
        //
        if (
            !empty($this->resources['templates']) &&
            is_array($this->resources['templates']) &&
            $mode & self::installerModeTemplates
        ) {
            foreach ($this->resources['templates'] as $item) {
                $this->_uninstallTemplate($item);
            }
        }

        //
        // Uninstall permissions
        //
        if (
            !empty($this->resources['permissions']) &&
            is_array($this->resources['permissions']) &&
            $mode & self::installerModePermissions
        ) {
            foreach ($this->resources['permissions'] as $item) {
                $this->_uninstallPermission($item);
            }
        }

        return ($this->errors('array')) ? false : true;    
    }

    /**
     * Save module(s) configiguration(s).
     *
     * @param array $item
     *
     */
    private function _addModuleConfigData(array $item) {
        $modules = $this->wire('modules');
        
        $moduleName = $item['name'];
        if (!$modules->isInstalled($moduleName) || !$modules->isConfigurable($moduleName)) return;
        
        $config = $modules->getConfig($moduleName);
        foreach ($item['options'] as $key => $value) {
            if (isset($config[$key]) && is_array($config[$key]) && is_array($value)) {
                $config[$key] = array_merge($config[$key], $value);
            } else {
                $config[$key] = $value;
            }
        }
        $modules->saveConfig($moduleName, $config);
    }
    
    /**
     * Install a template.
     *
     * @param array $item
     *
     */
    private function _installTemplate(array $item) {
        $fields = $this->wire('fields');
        $templates = $this->wire('templates');

        if (!$templates->get($item['name'])) {
            $fg = new Fieldgroup();
            $fg->name = $item['name'];
            // Add title field (mandatory!)
            $fg->add($fields->get('title'));
            $fg->save();             
           
            $t = new Template();
            $t->name = $item['name'];
            $t->fieldgroup = $fg;
            $t->label = $item['label'];
            if (isset($item['icon'])) $t->setIcon($item['icon']);
            if (isset($item['noChildren'])) $t->noChildren = $item['noChildren'];
            if (isset($item['noParents'])) $t->noParents = $item['noParents'];
            if (isset($item['tags'])) $t->tags = $item['tags'];
            $t->save();
            $message = sprintf($this->_('Installed template [%s].'), $item['name']);
            $this->message($message);
        } else {
            $message = sprintf($this->_('Template [%s] already exists. Skipped installation!'), $item['name']);
            $this->warning($message);
        }
    }
    
    /**
     * Uninstall a template.
     *
     * @param array $item
     *
     */
    private function _uninstallTemplate(array $item) {
        $templates = $this->wire('templates');
        $fieldgroups = $this->wire('fieldgroups');

        $t = $templates->get($item['name']);
        if (!$t) return;

        // Only delete template if not assigned to existing pages
        if ($templates->getNumPages($t) > 0) {
            $message = sprintf($this->_('Could not delete template [%s]. The template is assigned to at least one page!'), $item['name']);
            $this->warning($message);
        // All OK - delete!
        } else {
            $templates->delete($t);
            $fieldgroups->delete($t->fieldgroup); // delete the associated fieldgroup
            $message = sprintf($this->_('Deleted template [%s].'), $item['name']);
            $this->message($message);
        }
    }
    
    /**
     * Set template dependencies.
     *
     * @param array $item
     *
     */
    private function _setTemplateDependencies(array $item) {
        $templates = $this->wire('templates');

        $t = $templates->get($item['name']);
        if ($t) {
            $pt = array();
            if (!empty($item['_allowedParentTemplates'])) {
                foreach (explode(',', $item['_allowedParentTemplates']) as $ptn) {
                    $pt[] += $templates->get($ptn)->id; // needs to be added as array of template IDs
                }
                $t->parentTemplates = $pt;
            }
            $ct = array();
            if (!empty($item['_allowedChildTemplates'])) {
                foreach (explode(',', $item['_allowedChildTemplates']) as $ctn) {
                    $ct[] += $templates->get($ctn)->id; // needs to be added as array of template IDs
                }
                $t->childTemplates = $ct;
            }
            $t->save();
        }
    }

    /**
     * Install a file.
     *
     * @param array $file
     *
     */
    private function _installFile(array $file) {
        $config = $this->wire('config');

        $source = dirname(__FILE__) . DIRECTORY_SEPARATOR . $file['type'] . DIRECTORY_SEPARATOR . $file['name'];
        $destination = $config->paths->templates . $file['name'];
        if (!file_exists($destination)) {
            if ($this->wire('files')->copy($source, $destination)) {
                $message = sprintf($this->_('Installed file [%1$s] to [%2$s].'), $source, $destination);
                $this->message($message);
            } else {
                $message = sprintf($this->_('Could not copy file from [%1$s] to [%2$s]. Please copy manually.'), $source, $destination);
                $this->error($message);
            }
        } else {
            $message = sprintf($this->_('File [%2$s] already exists. Skipped installation! If necessary please copy manually from [%1$s].'), $source, $destination);
            $this->warning($message);
        }
    }
    
    /**
     * Uninstall a file.
     *
     * @param array $file
     *
     */
    private function _uninstallFile(array $file) {
        $config = $this->wire('config');

        $destination = $config->paths->templates . $file['name'];
        if (file_exists($destination)) {
            if ($this->wire('files')->unlink($destination)) {
                $message = sprintf($this->_('Removed file [%s].'), $destination);
                $this->message($message);
            } else {
                $message = sprintf($this->_('Could not remove file [%s]. Please remove this file manually.'), $destination);
                $this->warning($message);
            }
        }
    }
    
    /**
     * Install a field.
     *
     * @param array $file
     *
     */
    private function _installField(array $item) {
        $fields = $this->wire('fields');
        $templates = $this->wire('templates');
        $pages = $this->wire('pages');
        $modules = $this->wire('modules');

        if (!$fields->get($item['name']) && empty($item['_configureOnly'])) {
            $f = new Field();
            if (!$f->type = $modules->get($item['type'])) {
                $message = sprintf($this->_('Field [%1$s] could not be installed. Fieldtype [%2$s] not available. Skipped installation!'), $item['name'], $item['type']);
                $this->error($message);
                return;
            }
            $f->name = $item['name'];
            $f->label = $item['label'];
            if (isset($item['label2'])) $f->label2 = $item['label2'];
            if (isset($item['description'])) $f->description = $item['description'];
            if (isset($item['notes'])) $f->notes = $item['notes'];
            if (isset($item['icon'])) $f->setIcon($item['icon']);
            if (isset($item['collapsed'])) $f->collapsed = $item['collapsed'];
            if (isset($item['maxlength'])) $f->maxlength = $item['maxlength'];
            if (isset($item['rows'])) $f->rows = $item['rows'];
            if (isset($item['columnWidth'])) $f->columnWidth = $item['columnWidth'];
            if (isset($item['defaultValue'])) $f->defaultValue = $item['defaultValue'];
            if (isset($item['min'])) $f->min = $item['min'];
            if (isset($item['inputType'])) $f->inputType = $item['inputType'];
            if (isset($item['inputfield'])) $f->inputfield = $item['inputfield'];
            if (isset($item['labelFieldName'])) $f->labelFieldName = $item['labelFieldName'];
            if (isset($item['usePageEdit'])) $f->usePageEdit = $item['usePageEdit'];
            if (isset($item['addable'])) $f->addable = $item['addable'];
            if (isset($item['derefAsPage'])) $f->derefAsPage = $item['derefAsPage'];
            // Used for AsmSelect
            if (isset($item['parent_id'])) {
                if ($parent_id = $pages->get($item['parent_id'])->id) {
                    $f->parent_id = $parent_id;
                }
            }
            // Used for AsmSelect
            if (isset($item['template_id'])) {
                if ($templates->get($item['template_id'])) {
                    $f->template_id = $templates->get($item['template_id'])->id;
                }
            }
            if (isset($item['showCount'])) $f->showCount = $item['showCount'];
            if (isset($item['stripTags'])) $f->stripTags = $item['stripTags'];
            if (isset($item['textformatters']) && is_array($item['textformatters'])) $f->textformatters = $item['textformatters'];
            if (isset($item['required'])) $f->required = $item['required'];
            if (isset($item['extensions'])) $f->extensions = $item['extensions']; // for image and file fields
            if (isset($item['pattern'])) $f->pattern = $item['pattern'];
            if (isset($item['tags'])) $f->tags = $item['tags'];
            if (isset($item['taxesType'])) $f->taxesType = $item['taxesType'];
            $f->save();
            $message = sprintf($this->_('Installed field [%s].'), $item['name']);
            $this->message($message);
        } elseif (!empty($item['_configureOnly'])) {
            // do nothing
        } else{
            $message = sprintf($this->_('Field [%s] already exists. Skipped installation!'), $item['name']);
            $this->warning($message);
        }

        // Add field to templates */
        if (!empty($item['_addToTemplates'])) {
            foreach (explode(',', $item['_addToTemplates']) as $tn) {
                $t = $templates->get($tn);
                if ($t) {
                    $fg = $t->fieldgroup;
                    if ($fg->hasField($item['name'])) continue; // No need to add - already added!
                    $f = $fields->get($item['name']);
                    $fg->add($f);
                    $fg->save();
                } else {
                    $message = sprintf($this->_('Could not add field [%1$s] to template [%2$s]. The template does not exist!'), $item['name'], $tn);
                    $this->warning($message);
                }
            }
        }

        // Configure field in templates context (overriding field options per template) */
        if (!empty($item['_templateFieldOptions'])) {
            foreach ($item['_templateFieldOptions'] as $tn => $options) {
                $t = $templates->get($tn);
                if ($t) {
                    $fg = $t->fieldgroup;
                    if ($fg->hasField($item['name'])) {
                        $f = $fg->getField($item['name'], true);
                        if (isset($options['label'])) $f->label = $options['label'];
                        if (isset($options['notes'])) $f->notes = $options['notes'];
                        if (isset($options['columnWidth'])) $f->columnWidth = $options['columnWidth'];
                        if (isset($options['collapsed'])) $f->collapsed = $options['collapsed'];
                        $fields->saveFieldgroupContext($f, $fg);
                    } else {
                        $message = sprintf($this->_('Could not configure options of field [%1$s] in template context [%2$s]. The field is not assigned to template!'), $item['name'], $tn);
                        $this->warning($message);
                    }
                } else {
                    $message = sprintf($this->_('Could not configure options of field [%1$s] in template context [%2$s]. The template does not exist!'), $item['name'], $tn);
                    $this->warning($message);
                }
            }
        }
    }
    
    /**
     * Uninstall a field.
     *
     * @param array $file
     *
     */
    private function _uninstallField(array $item) {
        $fields = $this->wire('fields');
        $templates = $this->wire('templates');

        // First remove field from template(s) before deleting it
        if (isset($item['_addToTemplates'])) {
            foreach (explode(',', $item['_addToTemplates']) as $tn) {
                $t = $templates->get($tn);
                if ($t) {
                    $fg = $t->fieldgroup;
                    $fg->remove($fields->get($item['name']));
                    $fg->save();
                }
            }
        }
        if (!empty($item['_configureOnly'])) return;
        $f = $fields->get($item['name']);
        if ($f) {
            $fields->delete($f);
            $message = sprintf($this->_('Deleted field [%s].'), $item['name']);
            $this->message($message);
        }
    }
    
    /**
     * Install a page.
     *
     * @param array $item
     *
     */
    private function _installPage(array $item) {
        $templates = $this->wire('templates');
        $pages = $this->wire('pages');

        // Page "parent" key may have "string tags"
        $parent = \ProcessWire\wirePopulateStringTags(
            $item['parent'],
            array('snipWireRootUrl' => $this->snipWireRootUrl)
        );

        $t = $templates->get($item['template']);
        if (!$t) {
            $message = sprintf($this->_('Skipped installation of page [%1$s]. The template [%2$s] to be assigned does not exist!'), $item['name'], $item['template']);
            $this->error($message);
            return;
        }
        if (!$this->wire('pages')->get($parent)) {
            $message = sprintf($this->_('Skipped installation of page [%1$s]. The parent [%2$s] to be set does not exist!'), $item['name'], $parent);
            $this->error($message);
            return;
        }
        
        if (!$pages->findOne('name=' . $item['name'] . ', include=hidden')->id) {
            $page = new Page();
            $page->name = $item['name'];
            $page->template = $item['template'];
            $page->parent = $parent;
            $page->process = $this;
            $page->title = $item['title'];
            if (isset($item['status'])) $page->status = $item['status'];
            $page->save();
            $message = sprintf($this->_('Installed page [%s].'), $page->path);
            $this->message($message);
            
            // Populate page-field values
            if (!empty($item['fields']) && is_array($item['fields'])) {
                foreach ($item['fields'] as $fieldname => $value) {
                    if ($page->hasField($fieldname)) {
                        $type = $page->getField($fieldname)->type;
                        if ($type == 'FieldtypeImage') {
                            $source = dirname(__FILE__) . DIRECTORY_SEPARATOR . $value;
                            $page->$fieldname->add($source);
                        } else {
                            $page->$fieldname = $value;
                        }
                    }
                }
            }
            $page->save();
        } else {
            $message = sprintf($this->_('Page [%s] already exists. Skipped installation!'), $item['name']);
            $this->warning($message);
        }
    }

    /**
     * Uninstall a page.
     *
     * @param array $item
     *
     */
    private function _uninstallPage(array $item) {
        $pages = $this->wire('pages');

        $p = $pages->get('template=' . $item['template'] . ', name=' . $item['name']); 
        if ($p->id) {
            if (isset($item['_uninstall'])) {
                if (($item['_uninstall'] == 'delete' || $item['_uninstall'] == 'trash') && $p->hasStatus(Page::statusSystem)) {
                    $p->addStatus(Page::statusSystemOverride); 
                    $p->removeStatus(Page::statusSystem);
                    $p->removeStatus(Page::statusSystemOverride);
                }
                if ($item['_uninstall'] == 'delete') {
                    $p->delete(true); // including sub-pages
                    $message = sprintf($this->_('Deleted page [%s].'), $p->path);
                    $this->message($message);
                } elseif ($item['_uninstall'] == 'trash') {
                    $p->trash();
                    $message = sprintf($this->_('Trashed page [%s].'), $p->path);
                    $this->message($message);
                } elseif ($item['_uninstall'] == 'no') {
                    // do nothing!
                }
            }
        }
    }
    
    /**
     * Install a permission.
     *
     * @param array $item
     *
     */
    private function _installPermission(array $item) {
        $permissions = $this->wire('permissions');

        $permission = $permissions->get('name=' . $item['name']);
        if (!$permission) {
            $p = new Permission();
            $p->name = $item['name'];
            $p->title = $item['title'];
            $p->save();
            $message = sprintf($this->_('Installed permission [%s].'), $item['name']);
            $this->message($message);
        } else {
            $message = sprintf($this->_('Permission [%s] already exists. Skipped installation!'), $item['name']);
            $this->warning($message);
        }
    }
    
    /**
     * Uninstall a permission.
     *
     * @param array $item
     *
     */
    private function _uninstallPermission(array $item) {
        $permissions = $this->wire('permissions');

        $permission = $permissions->get('name=' . $item['name']);
        if ($permission){
            $permission->delete();
            $message = sprintf($this->_('Deleted permission [%s].'), $item['name']);
            $this->message($message);
        }
    }
}
