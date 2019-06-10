<?php namespace ProcessWire;

/**
 * Extended resources installer and uninstaller for SnipWire.
 * (This file is part of the SnipWire package)
 * 
 * Licensed under MPL 2.0 (see LICENSE file provided with this package)
 * Copyright 2019 by Martin Gartner
 *
 * ProcessWire 3.x, Copyright 2019 by Ryan Cramer
 * https://processwire.com
 *
 */

class ExtendedInstaller extends Wire {

    const installerModeTemplates = 1;
    const installerModeFields = 2;
    const installerModePages = 4;
    const installerModePermissions = 8;
    const installerModeFiles = 16;
    const installerModeAll = 31;

    /** @var array $resources Installation resources */
    protected $resources = array();
    
    /**
     * Constructor for ExtendedInstaller class.
     * 
     * @return void
     * 
     */
    public function __construct() {
        $this->getInstallResourcesExternal();
        parent::__construct();
    }

    /**
     * Retrieve extended installation resources from [ClassName].resources.php
     * (multidimensional array)
     * 
     * @return array
     * @throws WireException
     * 
     */
    protected function getInstallResourcesExternal() {
        // Wire method
        $className = $this->className();
        $resources = $className . '.resources.php';
        $path = dirname(__FILE__) . DIRECTORY_SEPARATOR . $resources;
        if (file_exists($path)) {
            include $path;
            if (!is_array($resources) || !count($resources)) {
                $out = sprintf($this->_("Installation aborted. Invalid [%s] file"), $resources);
                throw new WireException($out);
            }
        } else  {
            $out = sprintf($this->_("Installation aborted. Missing [%s] file."), $resources);
            throw new WireException($out);
        }
        $this->resources = $resources;
        return $this->resources;
    }


    /**
     * Installer for extended resources from [ClassName].resources.php.
     *
     * @param integer $mode
     * @return boolean true | false (if installations has errors or warnings)
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
        
        $sourceDir = dirname(__FILE__) . '/';
        
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
                    if (isset($item['tags'])) $t->tags = $item['tags'];
                    $t->save();
                    $this->message($this->_('Installed Template: ') . $item['name']);
                } else {
                    $this->message(sprintf($this->_("Template [%s] already exists. Skipped installation."), $item['name']));
                }
            }
            
            // Solve template dependencies (after installation of all templates!)
            foreach ($this->resources['templates'] as $item) {
                if ($t = $templates->get($item['name'])) {
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
                $source = $sourceDir . $file['type'] . '/' . $file['name'];
                $destination = $config->paths->templates . $file['name'];
                if (!file_exists($destination)) {
                    if ($this->wire('files')->copy($source, $destination)) {
                        $this->message(sprintf($this->_('Installed file [%1$s] to [%2$s].'), $source, $destination));
                    } else {
                        $this->warning(sprintf($this->_('Could not copy file from [%1$s] to [%2$s]. Please copy manually.'), $source, $destination));
                    }
                } else {
                    $this->message(sprintf($this->_('File [%2$s] already exists. If necessary please copy manually from [%1$s]. Skipped installation.'), $source, $destination));
                }
            }
        }

        /* ====== Install fields ====== */
        
        if (!empty($this->resources['fields']) && is_array($this->resources['fields']) && $mode & self::installerModeFields) {
            foreach ($this->resources['fields'] as $item) {
                if (!$fields->get($item['name'])) {
                    $f = new Field();
                    if (!$f->type = $modules->get($item['type'])) {
                        $this->message(sprintf($this->_("Field [%s] could not be installed. Fieldtype [%s] not available. Skipped installation."), $item['name'], $item['type']));
                        continue;
                    }
                    $f->name = $item['name'];
                    $f->label = $item['label'];
                    if (isset($item['label2'])) $f->label2 = $item['label2'];
                    if (isset($item['description'])) $f->description = $item['description'];
                    if (isset($item['notes'])) $f->notes = $item['notes'];
                    if (isset($item['collapsed'])) $f->collapsed = $item['collapsed'];
                    if (isset($item['maxlength'])) $f->maxlength = $item['maxlength'];
                    if (isset($item['rows'])) $f->rows = $item['rows'];
                    if (isset($item['columnWidth'])) $f->columnWidth = $item['columnWidth'];
                    if (isset($item['defaultValue'])) $f->defaultValue = $item['defaultValue'];
                    if (isset($item['min'])) $f->min = $item['min'];
                    if (isset($item['inputType'])) $f->inputType = $item['inputType'];
                    if (isset($item['showCount'])) $f->showCount = $item['showCount'];
                    if (isset($item['stripTags'])) $f->stripTags = $item['stripTags'];
                    if (isset($item['textformatters']) && is_array($item['textformatters'])) $f->textformatters = $item['textformatters'];
                    if (isset($item['required'])) $f->required = $item['required'];
                    if (isset($item['extensions'])) $f->extensions = $item['extensions']; // for image and file fields
                    if (isset($item['pattern'])) $f->pattern = $item['pattern'];
                    if (isset($item['tags'])) $f->tags = $item['tags'];
                    if (isset($item['taxesType'])) $f->taxesType = $item['taxesType'];
                    $f->save();
                    $this->message($this->_('Installed Field: ') . $item['name']);
                } else {
                    $this->message(sprintf($this->_("Field [%s] already exists. Skipped installation."), $item['name']));
                }

            }

            // Add fields to their desired templates */
            foreach ($this->resources['fields'] as $item) {
                if (!empty($item['_addToTemplates'])) {
                    foreach (explode(',', $item['_addToTemplates']) as $tn) {
                        if ($t = $templates->get($tn)) {
                            $fg = $t->fieldgroup;
                            if ($fg->hasField($item['name'])) continue; // No need to add - already added!
                            $f = $fields->get($item['name']);
                            $fg->add($f);
                            $fg->save();
                        } else {
                            $out = sprintf($this->_("Could not add field [%s] to template [%s]. The template does not exist!"), $item['name'], $tn);
                            $this->warning($out);
                        }
                    }
                }
            }
            
            // Configure fields in their templates context (overriding field options per template) */
            foreach ($this->resources['fields'] as $item) {
                if (!empty($item['_templateFieldOptions'])) {
                    foreach ($item['_templateFieldOptions'] as $tn => $options) {
                        if ($t = $templates->get($tn)) {
                            $fg = $t->fieldgroup;
                            if ($fg->hasField($item['name'])) {
                                $f = $fg->getField($item['name'], true);
                                if (isset($options['label'])) $f->label = $options['label'];
                                if (isset($options['notes'])) $f->notes = $options['notes'];
                                if (isset($options['columnWidth'])) $f->columnWidth = $options['columnWidth'];
                                $fields->saveFieldgroupContext($f, $fg);
                            } else {
                                $out = sprintf($this->_("Could not configure options of field [%s] in template context [%s]. The field is not assigned to template!"), $item['name'], $tn);
                                $this->warning($out);
                            }
                        } else {
                            $out = sprintf($this->_("Could not configure options of field [%s] in template context [%s]. The template does not exist!"), $item['name'], $tn);
                            $this->warning($out);
                        }
                    }
                }
            }            
        }

        /* ====== Install pages ====== */

        if (!empty($this->resources['pages']) && is_array($this->resources['pages']) && $mode & self::installerModePages) {
            foreach ($this->resources['pages'] as $item) {

                if (!$t = $templates->get($item['template'])) {
                    $out = sprintf($this->_("Skipped installation of page [%s]. The template [%s] to be assigned does not exist!"), $item['name'], $item['template']);
                    $this->warning($out);
                    continue;
                }
                if (!$this->wire('pages')->get($item['parent'])) {
                    $out = sprintf($this->_("Skipped installation of page [%s]. The parent [%s] to be set does not exist!"), $item['name'], $item['parent']);
                    $this->warning($out);
                    continue;
                }
                
                if (!$pages->findOne('name=' . $item['name'])->id) {
                    $page = new Page();
                    $page->name = $item['name'];
                    $page->template = $item['template'];
                    $page->parent = $item['parent'];
                    $page->process = $this;
                    $page->title = $item['title'];
                    $page->save();
                    $this->message($this->_('Installed Page: ') . $page->path);
                    
                    // Populate page-field values
                    if (!empty($item['fields']) && is_array($item['fields'])) {
                        foreach ($item['fields'] as $fieldname => $value) {
                            if ($page->hasField($fieldname)) {
                                $type = $page->getField($fieldname)->type;
                                if ($type == 'FieldtypeImage') {
                                    $source = $sourceDir . $value;
                                    $page->$fieldname->add($source);
                                } else {
                                    $page->$fieldname = $value;
                                }
                            }
                        }
                    }
                    $page->save();
                } else {
                    $this->message(sprintf($this->_("Page [%s] already exists. Skipped installation."), $item['name']));
                }
            }
        }

        /* ====== Install permissions ====== */

        if (!empty($this->resources['permissions']) && is_array($this->resources['permissions'])) {
            foreach ($this->resources['permissions'] as $item) {
                if (!$permission = $permissions->get('name=' . $item['name'])) {
                    $p = new Permission();
                    $p->name = $item['name'];
                    $p->title = $item['title'];
                    $p->save();
                    $this->message($this->_('Installed Permission: ') . $item['name']);
                } else {
                    $this->message(sprintf($this->_("Permission [%s] already exists. Skipped installation."), $item['name']));
                }
            }
        }
        
        return ($this->wire('notices')->hasWarnings() or $this->wire('notices')->hasErrors()) ? false : true;    
    }


    /**
     * Called when extended resources are uninstalled.
     *
     * @param integer $mode
     * @return void
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
        
        /* ====== Uninstall pages ====== */

        if (!empty($this->resources['pages']) && is_array($this->resources['pages']) && $mode & self::installerModePages) {
            foreach ($this->resources['pages'] as $item) {
                // Find the page to uninstall
                $p = $pages->get('template='.$item['template'] . ', name=' . $item['name']); 
        
                // If page was found, delete or trash it and let the user know
                if ($p->id) {
                    if (isset($item['_uninstall'])) {
                        if ($item['_uninstall'] == 'delete') {
                            $p->delete(true); // including sub-pages
                            $this->message('Deleted Page: '.$p->path);
                        } elseif ($item['_uninstall'] == 'trash') {
                            $p->trash();
                            $this->message('Trashed Page: ' . $p->path);
                        }
                    }
                }
            }
        }

        /* ====== Uninstall fields ====== */
        
        if (!empty($this->resources['fields']) && is_array($this->resources['fields']) && $mode & self::installerModeFields) {
            foreach ($this->resources['fields'] as $item) {
                // First remove field from template(s) before deleting it
                foreach (explode(',', $item['_addToTemplates']) as $tn) {
                    $t = $templates->get($tn);
                    $fg = $t->fieldgroup;
                    $fg->remove($fields->get($item['name']));
                    $fg->save();
                }
    
                // Now delete the field
                $f = $fields->get($item['name']);
                if ($f->id) {
                    $fields->delete($f);
                    $this->message('Deleted Field: ' . $item['name']);
                }
            }
        }

        /* ====== Uninstall files ====== */
        
        if (!empty($this->resources['files']) && is_array($this->resources['files']) && $mode & self::installerModeFiles) {
            foreach ($this->resources['files'] as $file) {
                $destination = $config->paths->templates . $file['name'];
                if (file_exists($destination)) {
                    if ($this->wire('files')->unlink($destination)) {
                        $out = sprintf($this->_("Removed file [%s]."), $destination);
                        $this->message($out);
                    } else {
                        $out = sprintf($this->_("Could not remove file [%s] to [%s]. Please remove this file manually."), $destination);
                        $this->warning($out);
                    }
                }
            }
        }
        
        /* ====== Uninstall templates ====== */
                
        if (!empty($this->resources['templates']) && is_array($this->resources['templates']) && $mode & self::installerModeTemplates) {
            foreach ($this->resources['templates'] as $item) {
                $t = $templates->get($item['name']);
                // Template exists?
                if (!$t->id) {
                    $out = sprintf($this->_("Could not delete template [%s]. The template does not exist!"), $item['name']);
                    $this->warning($out);
                // Only delete template if not assigned to existing pages
                } elseif ($templates->getNumPages($t) > 0) {
                    $out = sprintf($this->_("Could not delete template [%s]. The template is assigned to at least on page!"), $item['name']);
                    $this->warning($out);
                // All OK - delete!
                } else {
                    $templates->delete($t);
                    $fieldgroups->delete($t->fieldgroup); // delete the associated fieldgroup
                    $this->message('Deleted Template: ' . $item['name']);
                }
            }
        }

        /* ====== Uninstall permissions ====== */
        
        if (!empty($this->resources['permissions']) && is_array($this->resources['permissions']) && $mode & self::installerModePermissions) {
            foreach ($this->resources['permissions'] as $item) {
                // If permission was found, let the user know and delete it
                $permission = $permissions->get('name=' . $item['name']);
                if ($permission->id){
                    $permission->delete();
                    $this->message('Deleted Permission: ' . $item['name']);
                }
            }
        }

        return ($this->wire('notices')->hasWarnings() or $this->wire('notices')->hasErrors()) ? false : true;    
    }

}