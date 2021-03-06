<?php

/**                                                                       ````
 * This file is part of the "NFQ Bundles" package.
 *
 * (c) NFQ Technologies UAB <info@nfq.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Nfq\CmsPageBundle\Controller\Admin;

use Nfq\AdminBundle\PlaceManager\PlaceManagerInterface;
use Nfq\AdminBundle\Service\FormManager;
use Nfq\AdminBundle\Controller\Traits\CrudIndexController;
use Nfq\AdminBundle\Controller\Traits\TranslatableCRUDController;
use Nfq\CmsPageBundle\Entity\CmsPage;
use Nfq\CmsPageBundle\Service\CmsTypeManager;
use Nfq\CmsPageBundle\Service\Admin\CmsManager;
use Nfq\CmsPageBundle\Service\Adapters\CmsPageAdapterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Nfq\AdminBundle\Paginator\Paginator;
use Symfony\Component\HttpFoundation\Response;
use Nfq\CmsPageBundle\Service\CmsPlaceManager;

/**
 * Class CmsPageController
 * @package Nfq\CmsPageBundle\Controller\Admin
 */
class CmsPageController extends Controller
{

    private $admin_service_cms_manager;
    private $cms_type_manager;
    private $cms_manager;

    use TranslatableCRUDController {
        newAction as traitNewAction;
        createAction as traitCreateAction;
        updateAction as traitUpdateAction;
    }
    use CrudIndexController {
        indexAction as traitIndexAction;
    }

    /**
     * @var CmsPageAdapterInterface
     */
    private $adapter;

    private $service_place_manager;

    /**
     * @required
     */
    public function setDependencies(
        Paginator $paginator,
        CmsManager $admin_service_cms_manager,
        CmsTypeManager $cms_type_manager,
        CmsManager $cms_manager,
        CmsPlaceManager $service_place_manager)
    {
        $this->paginator = $paginator;
        $this->admin_service_cms_manager = $admin_service_cms_manager;
        $this->cms_type_manager = $cms_type_manager;
        $this->cms_manager = $cms_manager;
        $this->service_place_manager = $service_place_manager;
    }

    /**
     * Lists all entities.
     *
     * @Template()
     * @param Request $request
     * @return Response
     */
    public function indexAction(Request $request)
    {
        $response = $this->traitIndexAction($request);

        return $this->render('@NfqCmsPage/Admin/CmsPage/index.html.twig', $response + [
                'contentTypes' => $this->getTypeManager()->getTypes()
            ]);
    }

    /**
     * @return CmsTypeManager
     */
    private function getTypeManager()
    {
        return $this->cms_type_manager;
    }

    /**
     * @Template()
     *
     * @param Request $request
     * @return Response
     */
    public function newAction(Request $request)
    {
        $this->setAdapter($request);

        $res = $this->traitNewAction($request);

        if ($res instanceof Response) {
            return $res;
        }

        return $this->render('@NfqCmsPage/Admin/CmsPage/new.html.twig', $res);
    }

    /**
     * @param Request $request
     */
    private function setAdapter(Request $request)
    {
        $this->adapter = $this->getTypeManager()->getAdapterFromRequest($request);
    }

    /**
     * @Template()
     *
     * @param Request $request
     * @return Response
     */
    public function createAction(Request $request)
    {
        $this->setAdapter($request);

        $res = $this->traitCreateAction($request);

        if ($res instanceof Response) {
            return $res;
        }

        return $this->render('@NfqCmsPage/Admin/CmsPage/new.html.twig', $res);
    }

    /**
     * @Template()
     *
     * @param Request $request
     * @return Response
     */
    public function updateAction(Request $request, $id)
    {
        $this->setAdapter($request);

        $res = $this->traitUpdateAction($request, $id);

        if ($res instanceof Response) {
            return $res;
        }

        return $this->render('@NfqCmsPage/Admin/CmsPage/update.html.twig', $res);
    }

    /**
     * @inheritdoc
     */
    protected function getEditableEntityForLocale($id, $locale)
    {
        return $this->getAdminCmsManager()->getEditableEntity($id, $locale);
    }

    /**
     * @return CmsManager
     */
    private function getAdminCmsManager()
    {
        return $this->admin_service_cms_manager;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    protected function getIndexActionResultsArray(Request $request)
    {
        return $this->getAdminCmsManager()->getResults($request);
    }

    /**
     * @param CmsPage $entity
     * @return RedirectResponse
     */
    protected function redirectToPreview($entity)
    {
        $params = $this->cms_manager
            ->getCmsUrlParams($entity->getIdentifier(), $entity->getLocale());

        $params['_locale'] = $entity->getLocale();

        $url = $this->generateUrl('nfq_cmspage_view', $params);

        return new RedirectResponse($url);
    }

    /**
     * @param Request $request
     * @param CmsPage|null $entity
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    protected function redirectToIndex(Request $request, $entity = null)
    {
        $redirectParams = $this->getRedirectToIndexParams($request, $entity);

        $redirectUri = $this->generateUrl('nfq_cmspage_list', $redirectParams->all());

        return $this->redirect($redirectUri);
    }

    /**
     * {@inheritdoc}
     */
    protected function getCreateFormAndEntity($locale)
    {
        $formType = get_class($this->adapter->getFormTypeInstance());
        $entity = $this->adapter->getEntityInstance();
        $entity->setLocale($locale);

        $uri = $this->generateUrl('nfq_cmspage_create', ['_type' => $this->adapter->getType()]);

        $formOptions = [
            'locale' => $locale,
            'places' => $this->getPlaceManager()->getPlaceChoices(),
        ];

        $submit = ($entity->getIsPublic())
            ? FormManager::SUBMIT_STANDARD | FormManager::SUBMIT_CLOSE | FormManager::SUBMIT_PREVIEW
            : FormManager::SUBMIT_STANDARD | FormManager::SUBMIT_CLOSE;

        $formBuilder = $this
            ->getFormService()
            ->getFormBuilder($uri, FormManager::CRUD_CREATE, $formType, $entity, $formOptions, $submit);

        $this->adapter->modifyForm($formBuilder);

        return [$entity, $formBuilder->getForm()];
    }

    /**
     * {@inheritdoc}
     */
    protected function getEditDeleteForms($entity)
    {
        $formType = get_class($this->adapter->getFormTypeInstance());

        $id = $entity->getId();

        $formOptions = [
            'locale' => $entity->getLocale(),
            'places' => $this->getPlaceManager()->getPlaceChoices(),
        ];

        $uri = $this->generateUrl('nfq_cmspage_update', ['id' => $id, '_type' => $this->adapter->getType()]);

        $submit = ($entity->getIsPublic())
            ? FormManager::SUBMIT_STANDARD | FormManager::SUBMIT_CLOSE | FormManager::SUBMIT_PREVIEW
            : FormManager::SUBMIT_STANDARD | FormManager::SUBMIT_CLOSE;

        $formBuilder = $this
            ->getFormService()
            ->getFormBuilder($uri, FormManager::CRUD_UPDATE, $formType, $entity, $formOptions, $submit);

        $this->adapter->modifyForm($formBuilder);

        $deleteForm = $this->getDeleteForm($id);

        return [$formBuilder->getForm(), $deleteForm];
    }

    /**
     * {@inheritdoc}
     */
    protected function getDeleteForm($id)
    {
        $uri = $this->generateUrl('nfq_cmspage_delete', ['id' => $id]);

        return $this->getFormService()->getDeleteForm($uri);
    }

    /**
     * @param CmsPage $entity
     */
    protected function insertAfterCreateAction($entity)
    {
        $manager = $this->getAdminCmsManager();
        $manager->insert($entity);
    }

    /**
     * @param CmsPage $entity
     */
    protected function deleteAfterDeleteAction($entity)
    {
        $manager = $this->getAdminCmsManager();
        $manager->delete($entity);
    }

    /**
     * @param CmsPage $entity
     */
    protected function saveAfterUpdateAction($entity)
    {
        $manager = $this->getAdminCmsManager();
        $manager->save($entity);
    }

    /**
     * @return PlaceManagerInterface
     */
    private function getPlaceManager()
    {
        return $this->service_place_manager;
    }
}
