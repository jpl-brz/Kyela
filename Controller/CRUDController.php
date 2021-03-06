<?php
/*
 * Copyright 2016 Arnaud Bienvenu
 *
 * This file is part of Kyela.

 * Kyela is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.

 * Kyela is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.

 * You should have received a copy of the GNU Affero General Public License
 * along with Kyela.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Abienvenu\KyelaBundle\Controller;

use Abienvenu\KyelaBundle\Entity\Entity;
use Abienvenu\KyelaBundle\Entity\Poll;
use Abienvenu\KyelaBundle\Form\Type\FormActionsType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

abstract class CRUDController extends PollSetterController
{
	// Member variables that must be defined in the custom controller
	protected $entityName;
	protected $cancelRoute;
	protected $successRoute;
	protected $deleteRoute;
	protected $deleteSuccessRoute;

	// Methods to be implemented in the custom controller
	abstract public function newAction(Request $request);
	abstract public function editAction(Request $request, $id);
	abstract public function deleteAction(Request $request, $id);

	/**
	 * Adds pollUrl into the parameters if not explicitly set
	 *
	 * @param string $route
	 * @param mixed $parameters
	 * @param Boolean $absolute
	 */
	public function generateUrl($route, $parameters = [], $absolute = UrlGeneratorInterface::ABSOLUTE_PATH)
	{
		if (!isset($parameters['pollUrl']) && $this->poll)
		{
			$parameters['pollUrl'] = $this->poll->getUrl();
		}
		return parent::generateUrl($route, $parameters, $absolute);
	}

	/**
	 * Create a form to create a new entity, and create it when the form is submited
	 */
	protected function doNewAction($formType, Entity $entity, Request $request, $successMessage = null)
	{
		$form = $this->doCreateCreateForm($formType, $entity, $request->get('_route'));
		if ($request->isMethod('POST'))
		{
			$form->handleRequest($request);

			if ($entity instanceof Poll)
			{
				$this->poll = $entity;
			}
			else
			{
				$entity->setPoll($this->poll);
			}

			if ($form->get('actions')->has('cancel') && $form->get('actions')->get('cancel')->isClicked()) {
				return $this->redirect($this->generateUrl($this->cancelRoute));
			}

			if ($form->isValid()) {
				$em = $this->getDoctrine()->getManager();
				$em->persist($entity);
				$em->flush();

				if ($successMessage) {
					$request->getSession()->getFlashBag()->add('success', $successMessage);
				}
				return $this->redirect($this->generateUrl($this->successRoute));
			}
		}

		return [
			'poll'   => $this->poll,
			'entity' => $entity,
			'form'   => $form->createView(),
		];
	}

	/**
	 * Create a form to edit an entity, and update it when the form is submited
	 *
	 * @param FormTypeInterface $formType
	 * @param int $id The entity id
	 * @param Request $request
	 */
	protected function doEditAction($formType, $id, Request $request)
	{
		$em = $this->getDoctrine()->getManager();

		$entity = $em->getRepository($this->entityName)->find($id);

		if (!$entity) {
			throw $this->createNotFoundException("Unable to find entity.");
		}

		$deleteForm = $this->createDeleteForm($id);
		$editForm = $this->doCreateEditForm($formType, $entity, $request->get('_route'));
		if ($request->isMethod('PUT'))
		{
			$editForm->handleRequest($request);

			if ($editForm->get('actions')->get('cancel')->isClicked()) {
				$em->refresh($entity);
				return $this->redirect($this->generateUrl($this->cancelRoute));
			}

			if ($editForm->isValid()) {
				$em->flush();
				return $this->redirect($this->generateUrl($this->successRoute));
			}
			else {
				$em->refresh($entity);
			}
		}

		return array(
			'poll'        => $this->poll,
			'entity'      => $entity,
			'edit_form'   => $editForm->createView(),
			'delete_form' => $deleteForm->createView(),
		);
	}

	/**
	 * Deletes an entity
	 *
	 * @param Request $request
	 * @param mixed $id The entity id
	 *
	 */
	public function doDeleteAction(Request $request, $id)
	{
		$form = $this->createDeleteForm($id);
		$form->handleRequest($request);

		if ($form->isValid()) {
			$em = $this->getDoctrine()->getManager();
			$entity = $em->getRepository($this->entityName)->find($id);

			if (!$entity) {
				throw $this->createNotFoundException('Unable to find entity.');
			}

			$em->remove($entity);
			$em->flush();
			if ($entity instanceof Poll) {
				$this->unsetPoll($request);
			}
			$flashMessage = $this->get('translator')->trans('deleted');
			$request->getSession()->getFlashBag()->add('success', $flashMessage);
		}
		return $this->redirect($this->generateUrl($this->deleteSuccessRoute));
	}

	/**
	 * Creates a form to create an entity
	 *
	 * @param FormTypeInterface $formType The form builder
	 * @param Entity $entity The new entity
	 * @param string $action The name of the route to the action
	 *
	 * @return \Symfony\Component\Form\Form The form
	 */
	protected function doCreateCreateForm($formType, Entity $entity, $action)
	{
		$form = $this->createForm($formType, $entity, array(
			'action' => $this->generateUrl($action),
			'method' => 'POST',
		));

		$options = [
			'buttons' => [
				'save' => ['type' => SubmitType::class, 'options' => ['label' => 'create']],
			]
		];
		if (!($entity instanceof Poll))
		{
			$options['buttons']['cancel'] = ['type' => SubmitType::class, 'options' => ['label' => 'cancel', 'attr' => ['type' => 'default', 'novalidate' => true]]];
		}
		$form->add('actions', FormActionsType::class, $options);
		return $form;
	}

	/**
	 * Creates a form to edit an entity
	 *
	 * @param FormTypeInterface $formType The form builder
	 * @param Entity $entity The entity to edit
	 * @param string $action The name of the route to the action
	 *
	 * @return \Symfony\Component\Form\Form The form
	 */
	protected function doCreateEditForm($formType, Entity $entity, $action)
	{
		$form = $this->createForm($formType, $entity, array(
			'action' => $this->generateUrl($action, ['id' => $entity->getId()]),
			'method' => 'PUT',
		));

		$form->add('actions', FormActionsType::class, [
			'buttons' => [
				'save' => ['type' => SubmitType::class, 'options' => ['label' => 'save']],
				'cancel' => ['type' => SubmitType::class, 'options' => ['label' => 'cancel', 'attr' => ['type' => 'default', 'novalidate' => true]]],
			]
		]);
		return $form;
	}

	/**
	 * Creates a form to delete an entity by id.
	 *
	 * @param int $id The entity id
	 *
	 * @return \Symfony\Component\Form\Form The form
	 */
	protected function createDeleteForm($id)
	{
		$t = $this->get('translator');
		return $this->createFormBuilder()
		            ->setAction($this->generateUrl($this->deleteRoute, ['id' => $id]))
		            ->setMethod('DELETE')
		            ->add('submit', SubmitType::class, ['label' => 'delete', 'attr' => ['type' => 'danger', 'onclick' => "return confirm('{$t->trans("are.you.sure.to.delete")}');"]])
		            ->getForm();
	}
}
