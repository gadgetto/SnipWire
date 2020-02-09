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
    
    const installerModeTemplates = 1;
    const installerModeFields = 2;
    const installerModePages = 4;
    const installerModePermissions = 8;
    const installerModeFiles = 16;
    const installerModeAll = 31;

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
     * @return array
     * @throws WireException
     * 
     */
    public function setResourcesFile($fileName) {
        $path = dirname(__FILE__) . DIRECTORY_SEPARATOR . self::installerResourcesDirName . DIRECTORY_SEPARATOR . $fileName;
        if (file_exists($path)) {
            include $path;
            if (!is_array($resources) || !count($resources)) {
                $out = sprintf($this->_('Installation aborted. Invalid resources array in file [%s].'), $resources);
                throw new WireException($out);
            }
        } else  {
            $out = sprintf($this->_('Installation aborted. File [%s] not found.'), $resources);
            throw new WireException($out);
        }
        $this->resources = $resources;
        return $this->resources;
    }

    /**
     * Set installation resources from array.
     * (multidimensional array)
     * 
     * @param array $resources Installation resources array
     * @return array
     * @throws WireException
     * 
     */
    public function setResources($resources) {
        if (!is_array($resources) || !count($resources)) {
            $out = $this->_('Installation aborted. Invalid resources array.');
            throw new WireException($out);
        }
        $this->resources = $resources;
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
        $fields      = $this->wire('fields');
        $fieldgroups = $this->wire('fieldgroups');
        $templates   = $this->wire('templates');
        $pages       = $this->wire('pages');
        $permissions = $this->wire('permissions');
        $modules     = $this->wire('modules');
        $config      = $this->wire('config');
        
        if (!$this->resources) {
            $out = $this->_('Installation aborted. No resources array provided. Please use "setResourcesFile" or "setResources" method to provide a resources array.');
            throw new WireException($out);
        }
        
        $sourceBaseDir = dirname(__FILE__) . DIRECTORY_SEPARATOR;
        
        /* ====== Install templates ====== */
        
        if (!empty($this->resources['templates']) && is_array($this->resources['templates']) && $mode & self::installerModeTemplates) {
            foreach ($this->resources['templates'] as $item) {
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
                    $out = sprintf($this->_('Installed template [%s].'), $item['name']);
                    $this->message($out);
                } else {
                    $out = sprintf($this->_('Template [%s] already exists. Skipped installation!'), $item['name']);
                    $this->warning($out);
                }
            }
            
            // Solve template dependencies (after installation of all templates!)
            foreach ($this->resources['templates'] as $item) {
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
        }
        
        /* ====== Install files ====== */
        
        if (!empty($this->resources['files']) && is_array($this->resources['files']) && $mode & self::installerModeFiles) {
            foreach ($this->resources['files'] as $file) {
                $source = $sourceBaseDir . $file['type'] . DIRECTORY_SEPARATOR . $file['name'];
                $destination = $config->paths->templates . $file['name'];
                if (!file_exists($destination)) {
                    if ($this->wire('files')->copy($source, $destination)) {
                        $out = sprintf($this->_('Installed file [%1$s] to [%2$s].'), $source, $destination);
                        $this->message($out);
                    } else {
                        $out = sprintf($this->_('Could not copy file from [%1$s] to [%2$s]. Please copy manually.'), $source, $destination);
                        $this->error($out);
                    }
                } else {
                    $out = sprintf($this->_('File [%2$s] already exists. Skipped installation! If necessary please copy manually from [%1$s].'), $source, $destination);
                    $this->warning($out);
                }
            }
        }

        /* ====== Install fields ====== */
        
        if (!empty($this->resources['fields']) && is_array($this->resources['fields']) && $mode & self::installerModeFields) {
            foreach ($this->resources['fields'] as $item) {
                if (!empty($item['_configureOnly'])) continue;
                if (!$fields->get($item['name'])) {
                    $f = new Field();
                    if (!$f->type = $modules->get($item['type'])) {
                        $out = sprintf($this->_('Field [%1$s] could not be installed. Fieldtype [%2$s] not available. Skipped installation!'), $item['name'], $item['type']);
                        $this->error($out);
                        continue;
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
                        if (is_int($item['parent_id'])) {
                            $f->parent_id = $item['parent_id'];
                        } else {
                            $f->parent_id = $pages->get($item['parent_id'])->id;
                        }
                    }
                    // Used for AsmSelect
                    if (isset($item['template_id'])) {
                        if (is_int($item['template_id'])) {
                            $f->template_id = $item['template_id'];
                        } else {
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
                    $out = sprintf($this->_('Installed field [%s].'), $item['name']);
                    $this->message($out);
                } else {
                    $out = sprintf($this->_('Field [%s] already exists. Skipped installation!'), $item['name']);
                    $this->warning($out);
                }

            }

            // Add fields to their desired templates */
            foreach ($this->resources['fields'] as $item) {
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
                            $out = sprintf($this->_('Could not add field [%1$s] to template [%2$s]. The template does not exist!'), $item['name'], $tn);
                            $this->warning($out);
                        }
                    }
                }
            }
            
            // Configure fields in their templates context (overriding field options per template) */
            foreach ($this->resources['fields'] as $item) {
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
                                $out = sprintf($this->_('Could not configure options of field [%1$s] in template context [%2$s]. The field is not assigned to template!'), $item['name'], $tn);
                                $this->warning($out);
                            }
                        } else {
                            $out = sprintf($this->_('Could not configure options of field [%1$s] in template context [%2$s]. The template does not exist!'), $item['name'], $tn);
                            $this->warning($out);
                        }
                    }
                }
            }            
        }

        /* ====== Install pages ====== */

        if (!empty($this->resources['pages']) && is_array($this->resources['pages']) && $mode & self::installerModePages) {
            foreach ($this->resources['pages'] as $item) {

                // Page "parent" key may have "string tags"
                $parent = \ProcessWire\wirePopulateStringTags(
                    $item['parent'],
                    array('snipWireRootUrl' => $this->snipWireRootUrl)
                );

                $t = $templates->get($item['template']);
                if (!$t) {
                    $out = sprintf($this->_('Skipped installation of page [%1$s]. The template [%2$s] to be assigned does not exist!'), $item['name'], $item['template']);
                    $this->error($out);
                    continue;
                }
                if (!$this->wire('pages')->get($parent)) {
                    $out = sprintf($this->_('Skipped installation of page [%1$s]. The parent [%2$s] to be set does not exist!'), $item['name'], $parent);
                    $this->error($out);
                    continue;
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
                    $out = sprintf($this->_('Installed page [%s].'), $page->path);
                    $this->message($out);
                    
                    // Populate page-field values
                    if (!empty($item['fields']) && is_array($item['fields'])) {
                        foreach ($item['fields'] as $fieldname => $value) {
                            if ($page->hasField($fieldname)) {
                                $type = $page->getField($fieldname)->type;
                                if ($type == 'FieldtypeImage') {
                                    $source = $sourceBaseDir . $value;
                                    $page->$fieldname->add($source);
                                } else {
                                    $page->$fieldname = $value;
                                }
                            }
                        }
                    }
                    $page->save();
                } else {
                    $out = sprintf($this->_('Page [%s] already exists. Skipped installation!'), $item['name']);
                    $this->warning($out);
                }
            }
        }

        /* ====== Install permissions ====== */

        if (!empty($this->resources['permissions']) && is_array($this->resources['permissions'])) {
            foreach ($this->resources['permissions'] as $item) {
                $permission = $permissions->get('name=' . $item['name']);
                if (!$permission) {
                    $p = new Permission();
                    $p->name = $item['name'];
                    $p->title = $item['title'];
                    $p->save();
                    $out = sprintf($this->_('Installed permission [%s].'), $item['name']);
                    $this->message($out);
                } else {
                    $out = sprintf($this->_('Permission [%s] already exists. Skipped installation!'), $item['name']);
                    $this->warning($out);
                }
            }
        }
        
        return ($this->wire('notices')->hasErrors()) ? false : true;    
    }

    /**
     * Uninstaller for extended resources.
     *
     * @param integer $mode
     * @return boolean true | false (if uninstallation has errors)
     *
     */
    public function uninstallResources($mode = self::installerModeAll) {
        $fields      = $this->wire('fields');
        $fieldgroups = $this->wire('fieldgroups');
        $templates   = $this->wire('templates');
        $pages       = $this->wire('pages');
        $permissions = $this->wire('permissions');
        $modules     = $this->wire('modules');
        $config      = $this->wire('config');

        if (!$this->resources) {
            $out = $this->_('Uninstallation aborted. No resources array provided. Please use "setResourcesFile" or "setResources" method to provide a resources array.');
            throw new WireException($out);
        }

        /* ====== Uninstall pages ====== */

        if (!empty($this->resources['pages']) && is_array($this->resources['pages']) && $mode & self::installerModePages) {
            foreach ($this->resources['pages'] as $item) {
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
                            $out = sprintf($this->_('Deleted page [%s].'), $p->path);
                            $this->message($out);
                        } elseif ($item['_uninstall'] == 'trash') {
                            $p->trash();
                            $out = sprintf($this->_('Trashed page [%s].'), $p->path);
                            $this->message($out);
                        } elseif ($item['_uninstall'] == 'no') {
                            // do nothing!
                        }
                    }
                }
            }
        }

        /* ====== Uninstall fields ====== */

        if (!empty($this->resources['fields']) && is_array($this->resources['fields']) && $mode & self::installerModeFields) {
            foreach ($this->resources['fields'] as $item) {
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
                if (!empty($item['_configureOnly'])) continue;
                $f = $fields->get($item['name']);
                if ($f) {
                    $fields->delete($f);
                    $out = sprintf($this->_('Deleted field [%s].'), $item['name']);
                    $this->message($out);
                }
            }
        }

        /* ====== Uninstall files ====== */

        if (!empty($this->resources['files']) && is_array($this->resources['files']) && $mode & self::installerModeFiles) {
            foreach ($this->resources['files'] as $file) {
                $destination = $config->paths->templates . $file['name'];
                if (file_exists($destination)) {
                    if ($this->wire('files')->unlink($destination)) {
                        $out = sprintf($this->_('Removed file [%s].'), $destination);
                        $this->message($out);
                    } else {
                        $out = sprintf($this->_('Could not remove file [%s]. Please remove this file manually.'), $destination);
                        $this->warning($out);
                    }
                }
            }
        }

        /* ====== Uninstall templates ====== */

        if (!empty($this->resources['templates']) && is_array($this->resources['templates']) && $mode & self::installerModeTemplates) {
            foreach ($this->resources['templates'] as $item) {
                $t = $templates->get($item['name']);
                if (!$t) continue;
                // Only delete template if not assigned to existing pages
                if ($templates->getNumPages($t) > 0) {
                    $out = sprintf($this->_('Could not delete template [%s]. The template is assigned to at least one page!'), $item['name']);
                    $this->warning($out);
                // All OK - delete!
                } else {
                    $templates->delete($t);
                    $fieldgroups->delete($t->fieldgroup); // delete the associated fieldgroup
                    $out = sprintf($this->_('Deleted template [%s].'), $item['name']);
                    $this->message($out);
                }
            }
        }

        /* ====== Uninstall permissions ====== */

        if (!empty($this->resources['permissions']) && is_array($this->resources['permissions']) && $mode & self::installerModePermissions) {
            foreach ($this->resources['permissions'] as $item) {
                $permission = $permissions->get('name=' . $item['name']);
                if ($permission){
                    $permission->delete();
                    $out = sprintf($this->_('Deleted permission [%s].'), $item['name']);
                    $this->message($out);
                }
            }
        }

        return ($this->wire('notices')->hasErrors()) ? false : true;    
    }
}
