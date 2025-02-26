<?php
require_once ("PQSizeCtrl.php");
require_once ("PQTabWidget.php");
require_once ("PQCodeGen.php");
require_once ("PQGoToSlotDialog.php");
require_once ("PQSourceTextEdit.php");

class PQDesigner extends QMainWindow
{
    private $iconsPath;
    
    private $mainLayout;
    
    private $formarea;
    private $formareaLayout;
	private $formCreater;
    
    private $componentsLayout;
    private $componentsPanel;
    private $componentsDock;
    
    private $propertiesPanelLayout;
    private $propertiesPanel;
    private $propertiesDock;
    private $propertiesPanelWidgetLayout;
    private $propertiesPanelWidget;
    private $propertiesPanelWidgetTree;
    
    private $actionsPanel;
    private $actionsLayout;
    
    private $objHash;
    
    private $forms;
    private $objectList;
    private $formareaName = "___pq_creator__formarea_";
    private $lastEditedObject = null;
    private $startdragx;
    private $startdragy;
    private $sizeCtrl;
    private $gridSize = 8;
    
    private $componentsPath;
    private $componentEvents;
    private $componentFile;
    
    private $lastLoadedEvents;
        
    private $codegen;
    private $projectParentClass;
    private $projectDir;
    private $projectName;
    
    private $runningProcess;
    private $processCheckTimer;
    
    private $runAction;
    private $stopAction;
    private $buildAction;
    
    private $settingsAction;
    private $scriptsAction;
    private $designAction;
    private $sourceAction;
    
    private $formareaStack;
    
    private $sourceTextEdit;
    
    /* "Мёртвая дистанция", при которой компонента не будет перемещаться. */
    private $deadDragDistance = 8;
    
    /* Эффект отлипания перетаскиваемого компонента.
     * Если true - компонент "отлипает" от формы,
     * если false - компонент начинает перещение плавно. */
    private $detachEffect = true;
    
    public function __construct($projectParentClass = 'QWidget', $projectDir = '', $projectName = '')
    {
        parent::__construct();
        
        $fontDatabase = new QFontDatabase;
        $fontDatabase->addApplicationFont(__DIR__ . "/fonts/RobotoMono-Bold.ttf");
        $fontDatabase->addApplicationFont(__DIR__ . "/fonts/RobotoMono-BoldItalic.ttf");
        $fontDatabase->addApplicationFont(__DIR__ . "/fonts/RobotoMono-Italic.ttf");
        $fontDatabase->addApplicationFont(__DIR__ . "/fonts/RobotoMono-Light.ttf");
        $fontDatabase->addApplicationFont(__DIR__ . "/fonts/RobotoMono-LightItalic.ttf");
        $fontDatabase->addApplicationFont(__DIR__ . "/fonts/RobotoMono-Medium.ttf");
        $fontDatabase->addApplicationFont(__DIR__ . "/fonts/RobotoMono-MediumItalic.ttf");
        $fontDatabase->addApplicationFont(__DIR__ . "/fonts/RobotoMono-Regular.ttf");
        $fontDatabase->addApplicationFont(__DIR__ . "/fonts/RobotoMono-Thin.ttf");
        $fontDatabase->addApplicationFont(__DIR__ . "/fonts/RobotoMono-ThinItalic.ttf");
        
        /* Редактор исходных кодов */
        $this->sourceTextEdit = new PQSourceTextEdit;
        $this->sourceTextEdit->textEdit->enableRules();
        
        /* ... */
        $this->objHash = array();
        $this->iconsPath = c(PQNAME)->iconsPath;
        $this->componentsPath = c(PQNAME)->csPath;
        $this->componentEvents = c(PQNAME)->csEvents;
        $this->componentFile = c(PQNAME)->csName;
        $this->projectParentClass = $projectParentClass;
        $this->projectDir = $projectDir;
        $this->projectName = $projectName;
        
        $this->codegen = new PQCodeGen($projectParentClass, $this->objHash, '___pq_formwidget__centralwidget_form');
        $this->codegen->windowFlags = Qt::Tool;
        $this->codegen->show();
        
        $this->createToolBars();
        $this->createMenuBar();
        $this->createComponentsPanel();
        $formAreaWidget = $this->createFormarea();
        $this->createPropertiesDock();
        
        $this->mainLayout = new QVBoxLayout;
        $this->mainLayout->setMargin(0);
        $this->mainLayout->addWidget($this->actionsPanel);
        $this->mainLayout->addWidget($this->formareaStack);
        
        $this->centralWidget = new QWidget;
        $this->centralWidget->setLayout($this->mainLayout);
        
        $this->processCheckTimer = new QTimer;
        $this->processCheckTimer->interval = 280;
        $this->processCheckTimer->onTimer = function($timer, $event) {
            if($this->runningProcess != null) {
                $status = proc_get_status($this->runningProcess);
                if(!$status['running']) {
                    $this->pqStopAction(null, null, $status);
                }
            }
        };
        
        $this->resize(900, 600);
        $this->windowTitle = $projectName . ' - PQCreator';
        $this->objectName = '___pqcreator_mainwidget_';
        
        $this->show();
        
        $this->createForm($formAreaWidget, $projectParentClass, "Form 1");
    }
    
    public function createForm($formAreaWidget, $class, $windowTitle) {
        $nullSender = new QWidget();
        $nullSender->objectName = "nullSender_${class}_form";
        $point = $formAreaWidget->mapToGlobal($this->gridSize, $this->gridSize);
        
        $ex_props = array(
            '__pq_r_ex_enabled_' => true
        );
        
        $form = $this->createObject($nullSender, 0, 0, $point['x'], $point['y'], 0, $ex_props);
        $this->testCreate_ex($nullSender, 0, 0, $point['x']+$this->gridSize, $point['y']+$this->gridSize, 0, $formAreaWidget);
        
        if($form != null) {
            $this->lastEditedObject->tabIndex = $formAreaWidget->tabIndex;
            $this->lastEditedObject->draggable = false;
            $this->lastEditedObject->movable = false;
            $this->lastEditedObject->isMainWidget = true;
            $this->lastEditedObject->disabledSels = 'lt,lm,lb,rt,tm';
            $this->lastEditedObject->resize(400, 300);
            $this->lastEditedObject->windowTitle = $windowTitle;
            $this->selectObject($this->lastEditedObject);
        }
        
        $nullSender->free();
    }
	
	public function removeForm($object){
		$childObjects = $object->getChildObjects();
        if($childObjects != null) {
            foreach($childObjects as $childObject) {
                $this->deleteObject($childObject);
            }
        }

        $this->unselectObject();
        $objectName = $object->objectName;//print(print_r($object, true));
		$null = null;
		if(get_class($object) == 'QWidget' and $object->__IsDesignForm){
			if( $object->tabIndex>0 ){
				$this->formarea->currentIndex = 0;
				$this->formarea->removeTab( $object->tabIndex );
				$this->formarea->currentIndex = $object->tabIndex - 1;
				$this->formarea->stackCurrentIndex = $object->tabIndex == 0?0: $object->tabIndex - 2;
			} else {
				print(print_r(tr('Простите, но вы не можите удалить главную форму!'),true));
			}
		} else {
			print(print_r(tr('Простите, но вы не можите удалить то что не является формой!'),true));
		}
        unset($this->objHash[$objectName]);
        $this->objectList->removeItem($this->objectList->itemIndex($objectName));
        $object->free();
        $this->codegen->updateCode();
	}
    
    public function createFormarea() 
    {
        $widget = new QWidget;
        $widget->objectName = '___pq_formwidget__centralwidget_form';
        
        $null = null;
        $this->formarea = new PQTabWidget($this);
        $this->formarea->objectName = $this->formareaName;
        // $this->formarea->addTab($widget, $this->codegen, 'Form 1');
        $this->formarea->addTab($widget, $null, 'Form 1');
		
        $this->formareaStack = new QStackedWidget($this);
        $this->formareaStack->addWidget($this->formarea);
        $this->formareaStack->addWidget($this->codegen);
        
		$formCreater = new QWidget;
        $this->formarea->addTab($formCreater, $null, '', $this->iconsPath . '/new.png');
		$this->formCreater = $formCreater;
        return $widget;
    }
    
    public function createMenuBar()
    {
        $menubar = new QMenuBar($this);
        $filemenu = $menubar->addMenu(tr("File", "menubar"));
        $setsmenu = $menubar->addMenu(tr("Edit"));
        $openAction = $filemenu->addAction(tr("Open"));
        connect($openAction, SIGNAL('triggered(bool)') , $this, SLOT('aaacl(bool)'));
        $this->setMenuBar($menubar);
    }

    public function tabCloseRequested($sender, $index)
    {
        echo "tabCloseRequested $index";
    }

    public function createToolBars()
    {
        $topToolBar = new QToolBar($this);
        
        $this->buildAction = $topToolBar->addAction($this->iconsPath . '/build.png', tr('Build'));
        $this->buildAction->enabled = false;
        
        $this->stopAction = $topToolBar->addAction($this->iconsPath . '/stop.png', tr('Stop'));
        $this->stopAction->connect(SIGNAL('triggered(bool)') , $this, SLOT('pqStopAction(bool)'));
        $this->stopAction->enabled = false;
        
        $this->runAction = $topToolBar->addAction($this->iconsPath . '/run.png', tr('Run'));
        $this->runAction->connect(SIGNAL('triggered(bool)') , $this, SLOT('pqRunAction(bool)'));
        
        $topToolBar->addSeparator();
        
        $this->settingsAction = $topToolBar->addAction($this->iconsPath . '/settings.png', tr('Settings'));
        
        $topToolBar->addSeparator();
        
        $this->scriptsAction = $topToolBar->addAction($this->iconsPath . '/empty-scripts.png', tr('Scripts'));
        
        $topToolBar->addSeparator();
        
        $this->designAction = $topToolBar->addAction($this->iconsPath . '/design.png', tr('Design'));
        $this->designAction->connect(SIGNAL('triggered(bool)') , $this, SLOT('pqDesignAction(bool)'));
        $this->designAction->checkable = true;
        $this->designAction->checked = true;
        
        $this->sourceAction = $topToolBar->addAction($this->iconsPath . '/source.png', tr('Source'));
        $this->sourceAction->connect(SIGNAL('triggered(bool)') , $this, SLOT('pqSourceAction(bool)'));
        $this->sourceAction->checkable = true;
        
        $this->addToolBar(Qt::TopToolBarArea, $topToolBar);
    }
    
    public function pqDesignAction($sender, $checked)
    {
        if($checked) {
            $this->sourceAction->checked = false;
            $this->formareaStack->currentIndex = 0;
            $this->codegen->disableRules();
        }
        else {
            $sender->checked = true;
        }
    }
    
    public function pqSourceAction($sender, $checked)
    {
        if($checked) {
            $this->designAction->checked = false;
            $this->formareaStack->currentIndex = 1;
            $this->codegen->enableRules();
            $this->codegen->rehighlight();
        }
        else {
            $sender->checked = true;
        }
    }

    public function pqRunAction($sender, $checked)
    {
        $filename = $this->projectDir . '/main.php';
        $exec = $this->projectDir . '/pqengine.exe';
        if(file_put_contents($filename, $this->codegen->getCode()) === false) {
            $messagebox = new QMessageBox;
            $messagebox->warning(0, tr('PQCreator error'),
                                    sprintf( tr("Cannot write data to file: %1\r\n".
                                                'Please check that the file is not opened in another application'), 
                                            $filename )
                                );
                                
            $messagebox->free();
        }
        else {
            $pipes = array();
            $this->runningProcess = proc_open($exec, array(), $pipes, $this->projectDir);
            
            $this->runAction->enabled = false;
            $this->stopAction->enabled = true;
            
            $this->processCheckTimer->start();
        }
    }

    public function pqStopAction($sender, $checked, $status = null)
    {
        $this->processCheckTimer->stop();
        
        if($this->runningProcess != null) {
            if($status == null) {
                $status = proc_get_status($this->runningProcess);
            }
            
            if($status['running']) {
                exec('taskkill /F /T /PID ' . $status['pid']);
                $this->runningProcess = null;
            }
        }
        
        $this->runAction->enabled = true;
        $this->stopAction->enabled = false;
    }

    public function createComponentsPanel()
    {
        $this->componentsLayout = new QVBoxLayout;
        $this->componentsLayout->setMargin(2);
        
        $this->componentsPanel = new QWidget;
        $this->componentsPanel->width = 180;
        $this->componentsPanel->minimumWidth = 180;
        $this->componentsPanel->setLayout($this->componentsLayout);
        
        $this->loadComponents();
        $this->componentsPanel->adjustSize();
        
        $scrollArea = new QScrollArea;
        $scrollArea->setWidget($this->componentsPanel);
        
        $this->componentsDock = new QDockWidget($this);
        $this->componentsDock->setAllowedAreas(Qt::LeftDockWidgetArea | Qt::RightDockWidgetArea);
        $this->componentsDock->setWidget($scrollArea);
        $this->componentsDock->width = 180;
        $this->componentsDock->minimumWidth = 180;
        
        $this->addDockWidget(Qt::LeftDockWidgetArea, $this->componentsDock);
    }
    
    public function loadComponents()
    {
        $componentsPath = $this->componentsPath;
        if (is_dir($componentsPath)) {
            if ($dh = opendir($componentsPath)) {
                while (($component = readdir($dh)) !== false) {
                    if ($component == '.' || $component == '..') continue;
                    $cpath = "$componentsPath/$component";
                    if (is_dir($cpath)) {
                        $this->createButton($component);
                    }
                }
                closedir($dh);
            }
        }
        
       // $this->componentsLayout->addSpacer(0, 5000, QSizePolicy::Preferred, QSizePolicy::Expanding);
    }
    
    public function createButton($component)
    {
        $componentsPath = $this->componentsPath;
        $componentPath = "$componentsPath/$component/component.php";
        $r = array();
        if (file_exists($componentPath) && is_file($componentPath)) {
            include $componentPath;
        }
        else return;
        
        if (!isset($r['group']) || $r['group'] == 'NoVisual') {
            return;
        }

        if (isset($r['parent']) && !empty(trim($r['parent']))) {
            $parentClass = $r['parent'];
        }
        else {
            $parentClass = null;
        }

        $objectName = isset($r['objectName']) ? "pqcreatebutton_${component}_${r[objectName]}" : "pqcreatebutton_${component}_${component}";
        $buttonText = isset($r['title']) ? $r['title'] : $component;
        
        $button = new QPushButton($this->componentsPanel);
        $button->objectName = $objectName;
        $button->text = $buttonText;
        $button->styleSheet = "text-align: left";
        $button->minimumHeight = 30;
        $button->icon = "$componentsPath/$component/icon.png";
        $button->flat = true;
        $button->creator = true;
        $button->setIconSize(24,24);
        
        $button->parentClass = $parentClass;
        if (isset($r['defobjw']) && isset($r['defobjh'])) {
            $button->defobjw = $r['defobjw'];
            $button->defobjh = $r['defobjh'];
        }

        $button->connect(SIGNAL("mousePressed(int,int,int,int,int)") , $this, SLOT("createObject(int,int,int,int,int)"));
        $button->connect(SIGNAL("mouseMoved(int,int,int,int)") , $this, SLOT("moveObject(int,int,int,int)"));
        $button->connect(SIGNAL('mouseReleased(int,int,int,int,int)') , $this, SLOT('testCreate(int,int,int,int,int)'));
        
        $this->componentsLayout->addWidget($button);
    }

    public function selectObjectByListIndex($sender, $index)
    {
        if ($index == -1) return;
        $object = c($this->objectList->itemText($index));
        if ($object == NULL) return;
        $this->selectObject($object);
    }

    public function createPropertiesDock()
    {
        $this->propertiesDock = new QDockWidget($this);
        $this->propertiesDock->setAllowedAreas(Qt::LeftDockWidgetArea | Qt::RightDockWidgetArea);
        
        $this->propertiesPanelLayout = new QVBoxLayout;
        $this->propertiesPanelLayout->setMargin(0);
        
        $this->propertiesPanel = new QWidget;
        $this->propertiesPanel->minimumWidth = 180;
        $this->propertiesPanel->width = 180;
        $this->propertiesPanel->setLayout($this->propertiesPanelLayout);
        
        $this->objectList = new QComboBox($this->componentsPanel);
        $this->objectList->setIconSize(24, 24);
        $this->objectList->minimumHeight = 28;
        $this->objectList->connect(SIGNAL('currentIndexChanged(int)'), $this, SLOT('selectObjectByListIndex(int)'));
        $this->propertiesPanelLayout->addWidget($this->objectList);
        
        $this->createPropertiesPanel();
        
        $this->propertiesDock->setWidget($this->propertiesPanel);
        $this->addDockWidget(Qt::RightDockWidgetArea, $this->propertiesDock);
    }
    
    public function createPropertiesPanel()
    {
        if ($this->propertiesDock != null) {
            if ($this->propertiesPanelWidget != null) {
                $this->propertiesPanelWidget->free();
                $this->propertiesPanelWidget = null;
                $this->propertiesPanelWidgetLayout->free();
                $this->propertiesPanelWidgetLayout = null;
            }
        }
        else return;
        
        $this->propertiesPanelWidgetLayout = new QVBoxLayout;
        $this->propertiesPanelWidgetLayout->setMargin(0);
        
        $this->propertiesPanelWidget = new QWidget($this->propertiesPanel);
        $this->propertiesPanelWidget->minimumWidth = 180;
        $this->propertiesPanelWidget->width = 180;
        $this->propertiesPanelWidget->setLayout($this->propertiesPanelWidgetLayout);
        
        $this->propertiesPanelLayout->addWidget($this->propertiesPanelWidget);
    }

    public function aaacl($sender, $b)
    {
        echo 'OPEN!';
    }

    public function createObject($sender, $x, $y, $globalX, $globalY, $button, $ex_props = array())
    {
        $this->unselectObject();
        
        $e = explode("_", $sender->objectName);
        $type = $e[1];
        $objectName = $e[2];
        
        $index = 0;
        if (isset($this->objHash[$objectName])) {
            $index = 1;
            while (isset($this->objHash["${objectName}_$index"])) {
                $index++;
            }

            $objectName = "${objectName}_$index";
        }

        $obj = new $type;
        $obj->objectName = $objectName;
        $obj->setWindowFlags(Qt::Tool | Qt::WindowStaysOnTopHint | Qt::FramelessWindowHint);
        $obj->parentClass = $sender->parentClass;
        $obj->move($globalX, $globalY);
        $obj->windowOpacity = 0.6;
       // $obj->lockParentClassEvents = true;
        $obj->defaultPropertiesLoaded = false;
        $obj->draggable = true;
        
        foreach($ex_props as $key => $value) {
            $obj->$key = $value;
        }
        
        if ($sender->defobjw !== null 
                && $sender->defobjh !== null) {
            $obj->resize($sender->defobjw, $sender->defobjh);
        }

        $obj->setPHPEventListener($this, dynObjectEventListener);
        $obj->addPHPEventListenerType(QEvent::ContextMenu);
        $obj->addPHPEventListenerType(QEvent::KeyPress);
        $obj->addPHPEventListenerType(QEvent::MouseButtonPress);
        $obj->addPHPEventListenerType(QEvent::MouseButtonRelease);
        $obj->addPHPEventListenerType(QEvent::MouseMove);
        
        if($type == 'QWidget'
            || $type == 'QMainWindow') {
            
            $obj->addPHPEventListenerType(QEvent::Paint);
            $obj->addPHPEventListenerType(QEvent::Resize);
        }
        
        $obj->show();
        
        $objDataArr = array( 'object' => $obj, 
                             'events' => array(), 
                             'properties' => array(), 
                             'methods' => array() );
                             
        $objDataArr['properties'][] = 'objectName';
        $objDataArr['properties'][] = 'x';
        $objDataArr['properties'][] = 'y';
        $objDataArr['properties'][] = 'width';
        $objDataArr['properties'][] = 'height';
        
        $objData = new ArrayObject($objDataArr, ArrayObject::ARRAY_AS_PROPS);
        
        $this->objHash[$objectName] = $objData;
        $this->lastEditedObject = $obj;
        
        /* Смещение создаваемого компонента влево-наверх,
           чтобы он не перекрывался курсором */
        $this->startdragx = 5; 
        $this->startdragy = 5;
        
        return $obj;
    }

    public function testCreate($sender, $x, $y, $globalX, $globalY, $button)
    {
        return $this->testCreate_ex($sender, $x, $y, $globalX, $globalY, $button, null);
    }
    
    public function testCreate_ex($sender, $x, $y, $globalX, $globalY, $button, $widget) {
        $sender->releaseMouse();
        $obj = $this->lastEditedObject;
        
        if($widget == null) {
            $widget = $this->isFormarea($obj, $globalX, $globalY);
            
            if ($obj === $widget) {
                $this->selectObject($obj);
                return true;
            }
        }

        if ($widget != null) {
            $ppoint = $widget->mapFromGlobal($globalX - $this->startdragx, $globalY - $this->startdragy);
            $newObjX = floor($ppoint['x'] / $this->gridSize) * $this->gridSize;
            $newObjY = floor($ppoint['y'] / $this->gridSize) * $this->gridSize;
            $obj->draggable = false;
            $obj->setParent($widget);
            
            if ($widget->layout() != null) {
                $widget->layout()->addWidget($obj);
            }

            $obj->windowOpacity = 1;
            $obj->styleSheet = '';
            $obj->movable = true;
            $obj->move($newObjX, $newObjY);
            $obj->show();
            
            if (!$obj->defaultPropertiesLoaded) {
                $obj->isDynObject = true;
                $objectName = $obj->objectName;
                $component = get_class($obj);
                $icon = $this->componentsPath . "/$component/icon.png";
                
                $this->objectList->addItem($objectName, $icon);
                $this->objectList->currentIndex = $this->objectList->count() - 1;
            }

            $this->codegen->updateCode(); 
            $this->selectObject($obj);
            return true;
        }
        else {
            $this->lastEditedObject = null;
            $this->deleteObject($obj);
            return false;
        }
    }

    public function unselectObject($sender = 0, $x = 0, $y = 0, $globalX = 0, $globalY = 0, $btn = 0)
    {
        if ($this->sizeCtrl != null 
            && is_object($this->sizeCtrl)) {
            $this->sizeCtrl->free();
        }

        $this->sizeCtrl = null;
    }

    public function selectObject($object)
    {
        $this->unselectObject();
        $this->createPropertiesPanel();
        $this->lastEditedObject = $object;
        $this->sizeCtrl = new PQSizeCtrl($this->codegen, $object->parent, $object, $this->gridSize);
        $this->loadObjectProperties($object);
        $this->objectList->setCurrentText($object->objectName);
        $object->setFocus();
    }
    
    public function reselectObject()
    {
        $this->unselectObject();
        $object = $this->lastEditedObject;
        $this->sizeCtrl = new PQSizeCtrl($this->codegen, $object->parent, $object, $this->gridSize);
        $object->setFocus();
    }

    public function dynObjectEventListener($sender, $event)
    {
        switch ($event->type) {
        case QEvent::ContextMenu:
            // Запретить открывать меню, если указатель был смещен с объекта
            if ($sender != widgetAt(mousePos() ['x'], mousePos() ['y'])) {
                return true;
            }

            $this->selectObject($sender);
            $menu = new QMenu;
            
            $this->createGotoSlotAction($menu, $sender->objectName);
            
            $menu->addSeparator();
            
            $raiseAction = $menu->addAction(c(PQNAME)->qticonsPath . '/editraise.png', tr('To front'));
            $raiseAction->connect(SIGNAL('triggered(bool)'), $this, SLOT('raiseObject(bool)'));
            $raiseAction->__pq_objectName_ = $sender->objectName;
            
            $lowerAction = $menu->addAction(c(PQNAME)->qticonsPath . '/editlower.png', tr('To back'));
            $lowerAction->connect(SIGNAL('triggered(bool)'), $this, SLOT('lowerObject(bool)'));
            $lowerAction->__pq_objectName_ = $sender->objectName;
            
            $component = get_class($sender);
            if($component == 'QWidget'
                || $component == 'QGroupBox'
                || $component == 'QFrame'
                || $component == 'QMainWindow') {
                
                $menu_layout = $menu->addMenu(tr('Layout'));
                
                $action = $menu_layout->addAction(c(PQNAME)->qticonsPath . '/editbreaklayout.png', tr('Break layout'));
                $action->connect(SIGNAL('triggered(bool)') , $this, SLOT('menuLayoutAction(bool)'));
                $action->objectName = 'menuLayoutAction_NoLayout';
                
                $action = $menu_layout->addAction(c(PQNAME)->qticonsPath . '/editvlayout.png', tr('Vertical layout'));
                $action->connect(SIGNAL('triggered(bool)') , $this, SLOT('menuLayoutAction(bool)'));
                $action->objectName = 'menuLayoutAction_QVBoxLayout';
                
                $action = $menu_layout->addAction(c(PQNAME)->qticonsPath . '/edithlayout.png', tr('Horizontal layout'));
                $action->connect(SIGNAL('triggered(bool)') , $this, SLOT('menuLayoutAction(bool)'));
                $action->objectName = 'menuLayoutAction_QHBoxLayout';
            }
            
            $menu->exec(mousePos() ['x'], mousePos() ['y']);
            $menu->free();
            return true;
            
        case QEvent::KeyPress:
            if ($event->key === 16777223) { // Delete button
                $this->deleteObject($sender);
                $this->createPropertiesPanel();
            }
            return true;
            
        case QEvent::MouseButtonPress:
            if($event->button === Qt::LeftButton) {
                $this->startDrag($sender, $event->x, $event->y, $event->globalX, $event->globalY, $event->button);
                return true;
            }
        
        case QEvent::MouseButtonRelease:
            if($event->button === Qt::LeftButton) {
                $this->stopDrag($sender, $event->x, $event->y, $event->globalX, $event->globalY, $event->button);
                return true;
            }
            
        case QEvent::MouseMove:
            $this->moveObject($sender, $event->x, $event->y, $event->globalX, $event->globalY);
            return true;
            
        case QEvent::Paint:
            $painter = new QPainter($sender);
            $painter->setPen("#888", 1, Qt::SolidLine);
            $painter->drawPointBackground(8, 8, $sender->width, $sender->height);
            $painter->free();
            $sender->autoFillBackground = true;
            break;
            
        case QEvent::Resize:
            if($sender->isMainWidget === true) {
                $sender->parent->resize($sender->x + $sender->width + $this->gridSize, $sender->y + $sender->height + $this->gridSize);
            }
            break;
            
        default: 
            return false;
        }
        
        return false;
    }
    
    private function createGotoSlotAction($menu, $objectName) {
        $submenu = $menu->addMenu(tr('Goto slot...'));
        
        $startIndex = 10;
        
        $component = get_class(c($objectName));
        
        $events = array();
        $eventIndex = $startIndex;
        while ($component != null) {
            $componentPath = $this->componentsPath. "/$component/" . $this->componentFile;
            $eventsPath = $this->componentsPath . "/$component/" . $this->componentEvents;
            
            $r = array();
            
            if (file_exists($eventsPath) && is_file($eventsPath)) {
                require ($eventsPath);

                if (count($r) > 0) {
                    foreach($r as $event) {
                        $events[$eventIndex] = $event;
                        $events[$eventIndex]['component'] = $component;
                        
                        $icon = $this->iconsPath;
                        $icon .= ( isset($this->objHash[$objectName]->events[$event['event']])
                                    && !empty($this->objHash[$objectName]->events[$event['event']]['code']) )
                                ? '/non-empty-slot.png'
                                : '/empty-slot.png';
                        
                        $action = $submenu->addAction($icon, $event['event']);
                        
                        $action->connect(SIGNAL('triggered(bool)'), $this, SLOT('gotoSlotAction(bool)'));
                        $action->__pq_objectName_ = $objectName;
                        $action->__pq_eventName_ = $event['event'];
                        $action->__pq_eventArgs_ = $event['args'];
                        
                        $eventIndex++;
                    }
                }
                $submenu->addSeparator();
            }

            $component = null;
            require($componentPath);

            if (isset($r['parent']) && !empty(trim($r['parent']))) {
                $component = $r['parent'];
            }
        }
        
        $this->lastLoadedEvents = $events;
    }
    
    public function menuLayoutAction($sender, $bool)
    {
        $layout = $this->lastEditedObject->layout();
        if ($layout != null) {
            $layout->free();
        }
        
        $layoutClass = explode('_', $sender->objectName) [1];
        if ($layoutClass == 'NoLayout') {
            return;
        }

        $layout = new $layoutClass;
        $this->lastEditedObject->setLayout($layout);
        foreach($this->lastEditedObject->getChildObjects(false) as $widget) {
            $layout->addWidget($widget);
        }
        
        $this->codegen->updateCode();
    }

    public function startDrag($sender, $x, $y, $globalX, $globalY, $button)
    {
        $this->unselectObject();
        
        switch ($button) {
        case Qt::LeftButton:
            $this->lastEditedObject = $sender;
            
            if($sender->movable) {
                $sender->draggable = true;
                $sender->moved = false;
                
                $this->startdragx = $x;
                $this->startdragy = $y;
            }
            
            return true;
        }
    }

    public function stopDrag($sender, $x, $y, $globalX, $globalY, $button)
    {
        $sender->releaseMouse();
        
        switch ($button) {
        case Qt::LeftButton:
            $ok = false;
            if($sender->draggable
                && $sender->moved) {
                $ok = $this->testCreate($sender, $x, $y, $globalX, $globalY, $button);
            }
            else {
                $this->selectObject($sender);
                $ok = true;
            }
            
            if($ok) {
                $sender->draggable = false;
            }
            
            return true;
        }
    }

    public function raiseObject($sender, $bool)
    {
        c($sender->__pq_objectName_)->raise();
    }

    public function lowerObject($sender, $bool)
    {
        c($sender->__pq_objectName_)->lower();
    }
    
    function format_params($params) {
        $params_ex = explode(',', $params);
        $fparams = '';
        
        $count = count($params_ex) - 1;
        
        for($i = 0; $i <= $count; $i++)
        {
            $param = trim($params_ex[$i]);
            $param_ex = explode(' ', $param);
            
            $p1 = '<span style="color:#800080">' . $param_ex[0] . '</span>';
            $p2 = $param_ex[1];
            
            if(isset($param_ex[2]) && isset($param_ex[3])) $p2 .= " = ${param_ex[3]}";
            $fparams .= "$p1 <span style=\"color:#5555FF\">$p2</span>";
            
            if($i != $count) $fparams .= ", ";
        }
        
        return $fparams;
    }
    
    public function gotoSlotAction($sender, $bool) {
        $objectName = $sender->__pq_objectName_;
        $eventName = $sender->__pq_eventName_;
        $eventArgs = $sender->__pq_eventArgs_;
        $args = $this->format_params($eventArgs);
    
        $this->sourceTextEdit->textEdit->clear();
        
        if(isset($this->objHash[$objectName]->events[$eventName])) {
            $this->sourceTextEdit->textEdit->plainText = $this->objHash[$objectName]->events[$eventName]['code'];
        }
        
        $this->sourceTextEdit->headerLabel1->text = "<b>$eventName</b>( $args ) {";
        $this->sourceTextEdit->__pq_objectName_ = $objectName;
        $this->sourceTextEdit->__pq_eventName_ = $eventName;
        $this->sourceTextEdit->__pq_eventArgs_ = $eventArgs;
        
        $result = $this->sourceTextEdit->exec();
        
        if($result === 1) {
            $this->objHash[$objectName]->events[$eventName]['code'] = $this->sourceTextEdit->textEdit->plainText;
            
        }
        
        $this->codegen->updateCode();
    }
    
    public function PQGoToSlotDialogFinished($sender, $result) {
        if(isset($this->lastLoadedEvents[$result])) {
            echo "PQGoToSlotDialogFinished: " . $this->lastLoadedEvents[$result]['event'];
        }
    }
    
    public function isFormarea($object, $globalX, $globalY)
    {
        $wpoint = $this->mapFromGlobal($globalX, $globalY);
        $widget = $this->widgetAt($wpoint['x'], $wpoint['y']);
        
        if ($widget === $object) {
            $wpoint = $widget->parent->mapFromGlobal($globalX, $globalY);
            $widget = $widget->parent->widgetAt($wpoint['x'], $wpoint['y']);
        }

        if ($widget != NULL) {
            $parent = $widget;
            
            while ($parent != NULL) {
                //if ($parent->objectName != '___pq_formwidget__centralwidget_form1') {
                if ($parent->isFormAreaWidget !== true) {
                    $parent = $parent->parent;
                    continue;
                }

                $parentClass = get_class($widget);
                while ($parentClass != 'QWidget' 
                    && $parentClass != 'QFrame' 
                    && $parentClass != 'QGroupBox'
                    && $parentClass != 'PQTabWidget' 
                    && $parentClass != 'QMainWindow'
                    && $parentClass != 'QStackedWidget'
                    && $widget != NULL) {
                    
                    $widget = $widget->parent;
                    $parentClass = get_class($widget);
                }

                return $widget;
            }
        }

        return NULL;
    }

    public function moveObject($sender, $x, $y, $globalX, $globalY)
    {
        if (!empty($this->lastEditedObject) 
            && $this->lastEditedObject != null) {
            
            if ($sender->creator) {
                $sender = $this->lastEditedObject;
            }
            
            if ($sender->draggable) {
                if ($sender->isDynObject 
                    && !$sender->moved) {
                    
                    $ppoint = $sender->mapToGlobal(0, 0);
                    
                    $dx = $ppoint['x'] - ($globalX - $this->startdragx);
                    $dy = $ppoint['y'] - ($globalY - $this->startdragy);
                    
                    if(abs($dx) <= $this->deadDragDistance
                        && abs($dy) <= $this->deadDragDistance) {
                        
                        return;
                    }
                    else {
                        if(!$this->detachEffect) {
                            $this->startdragx -= $dx;
                            $this->startdragy -= $dy;
                        }
                    }
                    
                    $this->unselectObject();
                    
                    $sender->move($ppoint['x'], $ppoint['y']);
                    $sender->draggable = true;
                    $sender->setParent(0);
                    $sender->windowFlags = Qt::Tool | Qt::WindowStaysOnTopHint | Qt::FramelessWindowHint;
                    $sender->windowOpacity = 0.6;
                    $sender->show();
                    
                    $component = get_class($sender);
                    if ($component != 'QTextEdit' 
                        && $component != 'QTabWidget' 
                        && $component != 'QTableWidget') {
                        
                        $sender->grabMouse();
                    }
                    
                    $sender->moved = true;
                    
                    return;
                }
                
                $newx = $globalX - $this->startdragx;
                $newy = $globalY - $this->startdragy;
                
                // if($sender->isDynObject) {
                    if(!$this->isFormarea($sender, $globalX, $globalY)) {
                        $sender->styleSheet = 'background-color:#ff0000; border:1px solid #600000;';
                        $sender->windowOpacity = 0.4;
                    }
                    else {
                        $sender->styleSheet = '';
                        $sender->windowOpacity = 0.6;
                    }
                // }
                
                $sender->move($newx, $newy);
            }
        }
    }

    public function deleteObject($object)
    {
		if(get_class($object)=='QWidget' and $object->__IsDesignForm){
			$this->removeForm($object);
			return;
		}
		
        $childObjects = $object->getChildObjects();
        if($childObjects != null) {
            foreach($childObjects as $childObject) {
                $this->deleteObject($childObject);
            }
        }

        $this->unselectObject();
        $objectName = $object->objectName;//print(print_r($object, true));
        unset($this->objHash[$objectName]);
        $this->objectList->removeItem($this->objectList->itemIndex($objectName));
        $object->free();
        $this->codegen->updateCode();
    }

    
    private function createRootItemWidget($property, $object, $tree, $itemIndex) {
        $widget = null;
        
        switch ($property['type']) {
            case 'mixed':
            case 'int':
                $widget = new QLineEdit;
                if (isset($property['value']) && !$defaultPropertiesLoaded) {
                    $widget->text = $property['value'];
                }
                else {
                    $widget->text = $object->$property['property'];
                }

                // set validator if section exists

                if (isset($property['validator'])) {
                    $widget->setRegExpValidator($property['validator']);
                }
                else {

                    // if property type is `int` and validator section not exists,
                    // then set a default validator for integers

                    if ($property['type'] == 'int') {
                        $widget->setRegExpValidator('[0-9]*');
                    }
                }

                $widget->connect( SIGNAL('textChanged(QString)'), $this, SLOT('setObjectProperty(QString)') );
                break;

            case 'bool':
                $widget = new QCheckBox;
                if (isset($property['value']) && !$defaultPropertiesLoaded) {
                    $widget->checked = $property['value'];
                }
                else {
                    $widget->checked = $object->$property['property'];
                }

                $widget->connect( SIGNAL('toggled(bool)'), $this, SLOT('setObjectProperty(bool)') );
                break;
                
            case 'combo-list':
                foreach($property['list'] as $listItemProperty) {
                    $childItemIndex = $tree->addItem( $itemIndex, $listItemProperty['title'] );
                    $childItemWidget = $this->createRootItemWidget($listItemProperty, $object, $tree, $itemIndex);
                    
                    if ($childItemWidget != null) {
                        $childItemWidget->__pq_property_ = $property['property'];
                        $childItemWidget->__pq_propertyType_ = $property['type'];
                        $childItemWidget->objectName = "__pq_property_combo_"
                                                        . $property['property']
                                                        . "_"
                                                        . $listItemProperty['property'];
                        
                        $tree->setItemWidget($childItemIndex, 1, $childItemWidget);
                    }
                }
                break;
                
            case 'combo':
                $widget = new QComboBox;
                
                if(isset($property['list']) 
                    && is_array($property['list'])) {
                    
                    $index = 0;
                    foreach($property['list'] as $list) {
                        if(isset($list['title'])
                            && isset($list['value'])) {
                            
                            $cPropertyValue = "cPropertyValue_$index";
                            $qvalue = "__pq_property_qvalue_$index";
                            
                            $widget->addItem($list['title']);
                            $widget->$cPropertyValue = $list['value'];
                            $widget->$qvalue = $list['qvalue'];
                            
                            if(isset($property['defaultIndex'])) {
                                $widget->currentIndex = $property['defaultIndex'];
                            }
                            
                            $index++;
                        }
                    }
                }
                
                $widget->connect( SIGNAL('currentIndexChanged(int)'), $this, SLOT('setObjectProperty(int)') );
                break;
        }
            
        return $widget;
    }
    
    // TODO: добавить кэширование!
    public function loadObjectProperties($object)
    {
        $component = get_class($object);
        
        // Загружаем все свойства в массив
        
        $properties = array();
        while ($component != null) {
            $componentPath = $this->componentsPath . "/$component/component.php";
            $propertiesPath = $this->componentsPath . "/$component/properties.php";
            
            // TODO: убрать объявление тут, добавить во всех component.php
            $r = array();
            $r_ex = array();
            
            if (file_exists($propertiesPath) && is_file($propertiesPath)) {
                require ($propertiesPath);

                if (count($r) > 0) {
                    $properties[$component] = $r;
                    
                    if($object->__pq_r_ex_enabled_ === true) {
                        $properties[$component] = array_merge($properties[$component], $r_ex);
                    }
                }
            }

            $component = null;
            require ($componentPath);

            if (isset($r['parent']) && !empty(trim($r['parent']))) {
                $component = $r['parent'];
            }
        }

        // Отображаем все свойства на панели
        
        $tree = new QTreeWidget($this->propertiesPanelWidget);
        $tree->columnCount = 2;
        $tree->setHeaderLabels( array( tr('Property'), tr('Value') ) );
        
        foreach($properties as $c => $p) {
            $defaultPropertiesLoaded = $object->defaultPropertiesLoaded;
            $rootItemIndex = $tree->addRootItem($c);
            $tree->setFirstItemColumnSpanned($rootItemIndex, true);
            $tree->expandItem($rootItemIndex);
            
            foreach($p as $property) {
                //$itemIndex = $tree->addItem( $rootItemIndex, array($property['title'], '') );
                $itemIndex = $tree->addItem( $rootItemIndex, array($property['property'], '') );
                $widget = null;
                
                switch ($property['property']) {
                case 'text':
                case 'title':
                    if (!$defaultPropertiesLoaded) {
                        $objectName = $object->objectName;
                        if (isset($property['value'])) {
                            $object->$property['property'] = $property['value'];
                        }
                        else {
                            $object->$property['property'] = $objectName;
                        }
                        
                        if (!in_array($property['property'], $this->objHash[$objectName]->properties)) {
                            $this->objHash[$objectName]->properties[] = $property['property'];
                        }
                    }

                    break;
                }

                $widget = $this->createRootItemWidget($property, $object, $tree, $itemIndex);

                if ($widget != null) {
                    $widget->__pq_property_ = $property['property'];
                    $widget->__pq_propertyType_ = $property['type'];
                    $tree->setItemWidget($itemIndex, 1, $widget);
                }

                /* TODO: Не помню для чего это, но вроде оно реализовано в setObjectProperty() =D
                 * Скорее всего не понадобится
                if (!$defaultPropertiesLoaded) {
                    $objectName = $object->objectName;
                    if (!in_array($property['property'], $this->objHash[$objectName]->properties)) {
                        if (isset($property['value'])) {

                            $this->objHash[$objectName]->properties[] = $property['property'];

                        }
                    }
                }
                */
            }

            $this->propertiesPanelWidgetLayout->addWidget($tree);
        }

        if (!$defaultPropertiesLoaded) {
            $object->defaultPropertiesLoaded = true;
        }
    }

    public function setObjectProperty($sender, $value)
    {
        $property = $sender->__pq_property_;
        
        $object = $this->lastEditedObject;
        $objectName = $object->objectName;
        
        if ($property == "objectName") {
            $objData = $this->objHash[$objectName];
            unset($this->objHash[$objectName]);
            $this->objectList->setItemText($this->objectList->itemIndex($objectName) , $value);
            $this->objHash[$value] = $objData;
            $objectName = $value;
        }
        else if($property == "windowTitle") {
            $this->formarea->setTabText($object->tabIndex, $value);
        }

        // Methods
        if($sender->__pq_propertyType_ == 'combo-list') {
            preg_match_all('/(\%[0-9])/', $property, $args);
            
            if(is_array($args) 
                && isset($args[1])) {
                
                $args = $args[1];
                $argc = count($args);
                $preparedArgs = array();
                
                $method = explode('(',$property)[0];
                $preparedMethod = $property;
                for($argnum = 0; $argnum < $argc; $argnum++) {
                    $arg = $args[$argnum];
                    $arg_widget = c("__pq_property_combo_${property}_$arg");
                    $arg_widget_index = $arg_widget->currentIndex;
                    
                    $cPropertyValue = "cPropertyValue_" . $arg_widget_index;
                    $qvalue = "__pq_property_qvalue_" . $arg_widget_index;
                    
                    $preparedMethod = str_replace("%$argnum", $arg_widget->$qvalue, $preparedMethod);
                    $preparedArgs[] = $arg_widget->$cPropertyValue;
                }
                
                $object->__call( $method, $preparedArgs );
                
                $this->objHash[$objectName]->methods[$method] = $preparedMethod;
                $this->reselectObject();
            }
            
            else return;
        }
        
        // Properties
        else {
            if (!in_array($property, $this->objHash[$objectName]->properties)) {
                $this->objHash[$objectName]->properties[] = $property;
            }

            $object->$property = $value;
        }
        
        $this->codegen->updateCode();
    }
}