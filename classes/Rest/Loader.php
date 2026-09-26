<?php
namespace Jetpack_CRM_REST_API_Improved\Rest;
use Jetpack_CRM_REST_API_Improved\Plugin;
use Jetpack_CRM_REST_API_Improved\Rest\Controllers\ContactsController;
use Jetpack_CRM_REST_API_Improved\Rest\Controllers\CompaniesController;
use Jetpack_CRM_REST_API_Improved\Rest\Controllers\InvoicesController;
use Jetpack_CRM_REST_API_Improved\Rest\Controllers\LineItemsController;
use Jetpack_CRM_REST_API_Improved\Rest\Controllers\OpenApiController;
use Jetpack_CRM_REST_API_Improved\Rest\Controllers\QuotesController;
use Jetpack_CRM_REST_API_Improved\Rest\Controllers\TransactionsController;
use Jetpack_CRM_REST_API_Improved\Rest\Controllers\TasksController;
use Jetpack_CRM_REST_API_Improved\Rest\Controllers\LogsController;
use Jetpack_CRM_REST_API_Improved\Rest\Controllers\FormsController;
use Jetpack_CRM_REST_API_Improved\Rest\Controllers\SegmentsController;
use Jetpack_CRM_REST_API_Improved\Rest\Controllers\QuoteTemplatesController;
use Jetpack_CRM_REST_API_Improved\Rest\Controllers\TagsController;
use Jetpack_CRM_REST_API_Improved\Rest\Controllers\EmailsController;
use Jetpack_CRM_REST_API_Improved\Rest\Controllers\EmailThreadsController;
use Jetpack_CRM_REST_API_Improved\Rest\Controllers\EmailTemplatesController;
use Jetpack_CRM_REST_API_Improved\Rest\Controllers\BatchController;
use Jetpack_CRM_REST_API_Improved\Rest\Controllers\StatusController;
use Jetpack_CRM_REST_API_Improved\Rest\Controllers\SubresourcesController;
use Jetpack_CRM_REST_API_Improved\Rest\Controllers\TaskRemindersController;
use WP_REST_Controller;
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Loader {
	private $plugin;
	public function __construct( Plugin $plugin ) { $this->plugin = $plugin; }
	public function register_routes() {
		$controllers = array(
			new ContactsController( $this->plugin ),
			new CompaniesController( $this->plugin ),
			new InvoicesController( $this->plugin ),
			new QuotesController( $this->plugin ),
			new TransactionsController( $this->plugin ),
			new LineItemsController( $this->plugin ),
			new TasksController( $this->plugin ),
			new TaskRemindersController( $this->plugin ),
			new LogsController( $this->plugin ),
			new FormsController( $this->plugin ),
			new SegmentsController( $this->plugin ),
			new QuoteTemplatesController( $this->plugin ),
			new TagsController( $this->plugin ),
			new SubresourcesController( $this->plugin ),
			new EmailsController( $this->plugin ),
			new EmailThreadsController( $this->plugin ),
			new EmailTemplatesController( $this->plugin ),
			new BatchController( $this->plugin ),
			new StatusController( $this->plugin ),
			new OpenApiController( $this->plugin ),
		);
		$controllers = apply_filters( 'jpcrm_improved_rest_controllers', $controllers, $this->plugin );
		foreach ( $controllers as $controller ) {
			if ( $controller instanceof WP_REST_Controller ) {
				$controller->register_routes();
			}
		}
	}
}
