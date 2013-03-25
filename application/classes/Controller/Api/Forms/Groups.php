<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Ushahidi API Forms Groups Controller
 *
 * PHP version 5
 * LICENSE: This source file is subject to GPLv3 license
 * that is available through the world-wide-web at the following URI:
 * http://www.gnu.org/copyleft/gpl.html
 * @author     Ushahidi Team <team@ushahidi.com>
 * @package    Ushahidi - http://source.ushahididev.com
 * @subpackage Controllers
 * @copyright  Ushahidi - http://www.ushahidi.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License Version 3 (GPLv3)
 */

class Controller_API_Forms_Groups extends Ushahidi_API {

	/**
	 * Create a new group
	 * 
	 * POST /api/forms/:form_id/groups
	 * 
	 * @return void
	 */
	public function action_post_index_collection()
	{
		$form_id = $this->request->param('form_id');
		$results = array();
		$post = $this->_request_payload;
		
		$form = ORM::factory('Form', $form_id);
		
		if ( ! $form->loaded())
		{
			throw new Http_Exception_404('Invalid Form ID. \':id\'', array(
				':id' => $form_id,
			));
		}
		
		$group = ORM::factory('Form_Group')->values($post);
		$group->form_id = $form_id;
		
		// Validation - perform in-model validation before saving
		try
		{
			// Validate base group data
			$group->check();

			// Validates ... so save
			$group->values($post, array(
				'label', 'priority'
				));
			$group->save();

			// Response is the complete form
			$this->_response_payload = $group->for_api();
		}
		catch (ORM_Validation_Exception $e)
		{
			throw new Http_Exception_400('Validation Error: \':errors\'', array(
				'errors' => implode(', ', Arr::flatten($e->errors('models'))),
			));
		}
	}

	/**
	 * Retrieve all groups
	 * 
	 * GET /api/forms/:form_id/groups
	 * 
	 * @return void
	 */
	public function action_get_index_collection()
	{
		$form_id = $this->request->param('form_id');
		$results = array();

		$groups = ORM::factory('Form_Group')
			->order_by('id', 'ASC')
			->where('form_id', '=', $form_id)
			->find_all();

		$count = $groups->count();

		foreach ($groups as $group)
		{
			$results[] = $group->for_api();
		}

		// Respond with groups
		$this->_response_payload = array(
			'count' => $count,
			'results' => $results
			);
	}

	/**
	 * Retrieve a group
	 * 
	 * GET /api/forms/:form_id/groups/:id
	 * 
	 * @return void
	 */
	public function action_get_index()
	{
		$form_id = $this->request->param('form_id');
		$id = $this->request->param('id');
		$results = array();

		$group = ORM::factory('Form_Group')
			->where('form_id', '=', $form_id)
			->where('id', '=', $id)
			->find();

		if (! $group->loaded())
		{
			throw new Http_Exception_404('Group does not exist. Group ID: \':id\'', array(
				':id' => $id,
			));
		}

		// Respond with group
		$this->_response_payload =  $group->for_api();
	}

	/**
	 * Update a single group
	 * 
	 * PUT /api/forms/:form_id/groups/:id
	 * 
	 * @return void
	 */
	public function action_put_index()
	{
		$form_id = $this->request->param('form_id');
		$id = $this->request->param('id');
		$results = array();
		$post = $this->_request_payload;

		$group = ORM::factory('Form_Group')
			->where('form_id', '=', $form_id)
			->where('id', '=', $id)
			->find();

		if (! $group->loaded())
		{
			throw new Http_Exception_404('Group does not exist. Group ID: \':id\'', array(
				':id' => $id,
			));
		}
		
		// Load post values into group model
		$group->values($post);
		
		$group->id = $id;
		
		// Validation - perform in-model validation before saving
		try
		{
			// Validate base group data
			$group->check();

			// Validates ... so save
			$group->values($post, array(
				'label', 'priority'
				));
			$group->save();

			// Response is the complete form
			$this->_response_payload = $group->for_api();
		}
		catch (ORM_Validation_Exception $e)
		{
			// Error response
			$this->_response_payload = array(
				'errors' => implode(', ', Arr::flatten($e->errors('models')))
				);
		}
	}

	/**
	 * Delete a single group
	 * 
	 * DELETE /api/forms/:form_id/groups/:id
	 * 
	 * @return void
	 */
	public function action_delete_index()
	{
		$id = $this->request->param('id');
		$form_id = $this->request->param('form_id');

		$group = ORM::factory('Form_Group')
			->where('form_id', '=', $form_id)
			->where('id', '=', $id)
			->find();

		$this->_response_payload = array();
		if ( $group->loaded() )
		{
			// Return the group we just deleted (provides some confirmation)
			$this->_response_payload = $group->for_api();
			$group->delete();
		}
		else
		{
			throw new Http_Exception_404('Group does not exist. Group ID: \':id\'', array(
				':id' => $id,
			));
		}
	}
	
	/**
	 * Retrieve group's attributes
	 * 
	 * GET /api/forms/:form_id/groups/:id/attributes
	 * 
	 * @todo share code between this and POST /api/attributes/:id
	 * @return void
	 */
	public function action_get_attributes()
	{
		$form_id = $this->request->param('form_id');
		$id = $this->request->param('id');
		$results = array();

		$form = ORM::factory('Form', $form_id);
		
		if ( ! $form->loaded())
		{
			throw new Http_Exception_404('Invalid Form ID. \':id\'', array(
				':id' => $form_id,
			));
		}

		$group = ORM::factory('Form_Group')
			->where('form_id', '=', $form_id)
			->where('id', '=', $id)
			->find();

		if (! $group->loaded())
		{
			throw new Http_Exception_404('Group does not exist. Group ID: \':id\'', array(
				':id' => $id,
			));
		}

		$attributes = $group->form_attributes->find_all();
		
		$count = $attributes->count();

		foreach ($attributes as $attribute)
		{
			$results[] = $attribute->for_api();
		}

		// Respond with attributes
		$this->_response_payload = array(
			'count' => $count,
			'results' => $results
			);
	}
	
	/**
	 * Add new attribute to group
	 * 
	 * POST /api/forms/:form_id/groups/:id/attributes
	 * 
	 * @todo share code between this and POST /api/forms/:form_id/attributes
	 * @return void
	 */
	public function action_post_attributes()
	{
		$form_id = $this->request->param('form_id');
		$group_id = $this->request->param('id');
		$results = array();
		$post = $this->_request_payload;
		
		$form = ORM::factory('Form', $form_id);
		
		if ( ! $form->loaded())
		{
			throw new Http_Exception_404('Invalid Form ID. \':id\'', array(
				':id' => $form_id,
			));
		}

		$group = ORM::factory('Form_Group')
			->where('form_id', '=', $form_id)
			->where('id', '=', $group_id)
			->find();

		if (! $group->loaded())
		{
			throw new Http_Exception_404('Group does not exist. Group ID: \':id\'', array(
				':id' => $group_id,
			));
		}
		
		$attribute = ORM::factory('Form_Attribute')->values($post);
		
		// Validation - perform in-model validation before saving
		try
		{
			// Validate base group data
			$attribute->check();

			// Validates ... so save
			$attribute->values($post, array(
				'key', 'label', 'input', 'type'
				));
			$attribute->save();

			// Add relations
			$group->add('form_attributes', $attribute);
			$form->add('form_attributes', $attribute);

			// Response is the complete form
			$this->_response_payload = $attribute->for_api();
		}
		catch (ORM_Validation_Exception $e)
		{
			throw new Http_Exception_400('Validation Error: \':errors\'', array(
				'errors' => implode(', ', Arr::flatten($e->errors('models'))),
			));
		}
	}
}