<?php
App::uses('Router', 'Routing');
App::uses('CakeRequest', 'Network');
App::uses('CakeResponse', 'Network');
App::uses('Controller', 'Controller');

App::uses('CakeEventManager', 'Event');

App::uses('ComponentCollection', 'Controller');
App::uses('Component', 'Controller');
App::uses('CrudComponent', 'Crud.Controller/Component');

App::uses('Model', 'Model');

App::uses('Validation', 'Utility');

class TestCrudEventManager extends CakeEventManager {

	protected $log = array();

	public function dispatch($event) {
		$this->log[]= array(
			'name' => $event->name(),
			'subject' => $event->subject()
		);
		parent::dispatch($event);
	}

	public function getLog($params = array()) {
		$params += array('clear' => true, 'format' => 'names');

		$log = $this->log;

		if ($params['format'] === 'names') {
			$return = array();
			foreach($log as $entry) {
				$return[] = $entry['name'];
			}
			$log = $return;
		}

		if ($params['clear']) {
			$this->log = array();
		}

		return $log;
	}
}

class TestCrudComponent extends CrudComponent {

	/**
	 * Test visibility wrapper
	 */
	public function testGetSubject($additional = array()) {
		return $this->getSubject($additional);
	}

	/**
	 * Test visibility wrapper
	 */
	public function testRedirect($subject, $url = null) {
		return $this->redirect($subject, $url);
	}

	/**
	 * Test visibility wrapper
	 */
	public function testValidateId($id) {
		return $this->validateId($id);
	}
}

/**
 * Crud Test Case
 *
 */
class CrudTestCase extends CakeTestCase {

	public $fixtures = array('core.post');

	/**
	 * setUp method
	 *
	 * @return void
	 */
	public function setUp() {
		parent::setUp();

		CakeEventManager::instance(new TestCrudEventManager());

		ConnectionManager::getDataSource('test')->getLog();

		$this->model = ClassRegistry::init(array(
			'class' => 'Model',
			'alias' => 'CrudExample',
			'type' => 'Model',
			'table' => 'posts'
		));

		$this->controller = $this->getMock(
			'Controller',
			array('header', 'redirect', 'render', '_stop'),
			array(),
			'',
			false
		);
		$this->controller->name = 'CrudExamples';

		$this->request = new CakeRequest();
		$response = new CakeResponse();
		$this->controller->__construct($this->request, $response);
		$this->controller->methods = array();

		$Collection = new ComponentCollection();
		$Collection->init($this->controller);
		$settings = array(
			'actions' => array(
				'index',
				'add',
				'edit',
				'view',
				'delete'
			)
		);
		$this->controller->Components = $Collection;

		$this->Crud = $this->getMock(
			'TestCrudComponent',
			null,
			array($Collection, $settings)
		);

		$this->Crud->initialize($this->controller);
	}

	/**
	 * tearDown method
	 *
	 * @return void
	 */
	public function tearDown() {
		unset($this->Crud);
		parent::tearDown();
	}

	public function testDefaultSettings() {
		// TODO The SUT should throw an exception if actions is not defined
	}

	public function testEnableAction() {
		// TODO
	}

	public function testDisableAction() {
		// TODO
	}

	public function testMapActionView() {
		// TODO
	}

	public function testMapAction() {
		// TODO
	}

	public function testIsActionMapped() {
		// TODO
	}

	public function testGetIdFromRequest() {
		// TODO
	}

	public function testAddAction() {
	}

	/**
	 * Add a dummy detector to the request object so it says it's a delete request
	 */
	public function testDeleteActionExists() {
		$this->controller
			->expects($this->once())
			->method('render')
			->with('delete');

		$this->controller->request->addDetector('delete', array(
			'callback' => function() { return true; }
		));

		$this->Crud->settings['validateId'] = 'notUuid';
		$id = 1;

		$this->Crud->executeAction('delete', array($id));

		$count = $this->model->find('count', array('conditions' => array('id' => $id)));
		$this->assertSame(0, $count);

		$events = CakeEventManager::instance()->getLog();
		$index = array_search('Crud.beforeDelete', $events);
		$this->assertNotSame(false, $index, "There was no Crud.beforeDelete event triggered");

		$index = array_search('Crud.afterDelete', $events);
		$this->assertNotSame(false, $index, "There was no Crud.afterDelete event triggered");
	}

	public function testDeleteActionDoesNotExists() {
		$this->controller
			->expects($this->once())
			->method('render')
			->with('delete');

		$this->controller->request->addDetector('delete', array(
			'callback' => function() { return true; }
		));

		$this->Crud->settings['validateId'] = 'notUuid';
		$id = 42;
		$this->Crud->executeAction('delete', array($id));

		$events = CakeEventManager::instance()->getLog();
		$index = array_search('Crud.beforeDelete', $events);
		$this->assertNotSame(false, $index, "There was no Crud.beforeDelete event triggered");

		$index = array_search('Crud.recordNotFound', $events);
		$this->assertNotSame(false, $index, "A none-existent row did not trigger a Crud.recordNotFount event");
	}

	public function testEditActionGet() {
		$this->controller
			->expects($this->once())
			->method('render')
			->with('form');

		$this->Crud->settings['validateId'] = 'notUuid';
		$id = 1;

		$this->Crud->executeAction('edit', array($id));
	}

	public function testEditActionPost() {
		$this->controller
			->expects($this->once())
			->method('render')
			->with('form');

		$this->controller->request->addDetector('put', array(
			'callback' => function() { return true; }
		));

		$this->controller->data = array(
			'CrudExample' => array(
				'id' => 1,
				'title' => __METHOD__
			)
		);

		$this->Crud->settings['validateId'] = 'notUuid';
		$id = 1;

		$this->Crud->executeAction('edit', array($id));

		$this->model->id = $id;
		$result = $this->model->field('title');
		$this->assertSame(__METHOD__, $result);

		$events = CakeEventManager::instance()->getLog();

		$index = array_search('Crud.afterSave', $events);
		$this->assertNotSame(false, $index, "There was no Crud.afterSave event triggered");
	}

	public function testViewAction() {
		$this->controller
			->expects($this->once())
			->method('render')
			->with('view');

		$this->Crud->settings['validateId'] = 'notUuid';
		$id = 1;

		$this->Crud->executeAction('view', array($id));
	}

	public function testIndexAction() {
		$this->controller
			->expects($this->once())
			->method('render')
			->with('index');

		$this->request->params['named']= array();

		$this->Crud->executeAction('index');

		$events = CakeEventManager::instance()->getLog();

		$index = array_search('Crud.afterPaginate', $events);
		$this->assertNotSame(false, $index, "There was no Crud.afterPaginate event triggered");
	}

	public function testRedirect() {
		$subject = $this->Crud->testGetSubject();
		$this->Crud->testRedirect($subject);
	}

	public function testvalidateIdIntValid() {
		$this->controller->expects($this->never())->method('redirect');

		$this->Crud->settings['validateId'] = 'notUuid';

		$id = 1;
		$return = $this->Crud->testValidateId($id, 'int');
		$this->assertTrue($return, "Expected id $id to be accepted, it was rejected");
	}

	public function testvalidateIdIntInvalid() {
		$this->controller->expects($this->once())->method('redirect');

		$this->Crud->settings['validateId'] = 'notUuid';

		$id = 'abc';
		$return = $this->Crud->testValidateId($id, 'int');
		$this->assertFalse($return, "Expected id $id to be rejected, it was accepted");
	}

	public function testvalidateIdUUIDValid() {
		$this->controller->expects($this->never())->method('redirect');

		$id = '12345678-1234-1234-1234-123456789012';
		$return = $this->Crud->testValidateId($id);
		$this->assertTrue($return, "Expected id $id to be accepted, it was rejected");
	}

	public function testvalidateIdUUIDInvalid() {
		$this->controller->expects($this->once())->method('redirect');

		$id = 123;
		$return = $this->Crud->testValidateId($id, 'int');
		$this->assertFalse($return, "Expected id $id to be rejected, it was accepted");
	}
}
