<?php
// AppBundle/Controller/Binding/SettlementController.php
namespace AppBundle\Controller\Binding;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route,
    Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;

use Symfony\Component\HttpFoundation\Request,
    Symfony\Component\HttpFoundation\RedirectResponse,
    Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException,
    Symfony\Bundle\FrameworkBundle\Controller\Controller;

use AppBundle\Controller\Utility\Traits\ClassOperationsTrait,
    AppBundle\Service\Security\Utility\Interfaces\UserRoleListInterface,
    AppBundle\Entity\Region\Region,
    AppBundle\Entity\School\School,
    AppBundle\Security\Authorization\Voter\RegionVoter,
    AppBundle\Security\Authorization\Voter\SettlementVoter,
    AppBundle\Service\Security\SettlementBoundlessAccess;

class SettlementController extends Controller implements UserRoleListInterface
{
    use ClassOperationsTrait;

    public function showAction($objectClass, $objectId)
    {
        $_settlementBoundlessAccess = $this->get('app.security.settlement_boundless_access');

        if( !$_settlementBoundlessAccess->isGranted(SettlementBoundlessAccess::SETTLEMENT_READ) )
            throw $this->createAccessDeniedException('Access denied');

        $_manager = $this->getDoctrine()->getManager();

        switch(TRUE)
        {
            case $this->compareObjectClassNameToString(new Region, $objectClass):
                $object = $_manager->getRepository('AppBundle:Region\Region')->find($objectId);

                if( !$object )
                    throw $this->createNotFoundException("Region identified by `id` {$objectId} not found");

                $settlements = $_manager->getRepository('AppBundle:Settlement\Settlement')->findBy(['region' => $object]);

                $action = [
                    'path'  => 'settlement_choose',
                    'voter' => RegionVoter::REGION_BIND
                ];
            break;

            default:
                throw new NotAcceptableHttpException("Object not supported");
            break;
        }

        return $this->render('AppBundle:Entity/Settlement/Binding:show.html.twig', [
            'standalone'  => TRUE,
            'settlements' => $settlements,
            'object'      => $object,
            'action'      => $action
        ]);
    }

    /**
     * @Method({"GET"})
     * @Route(
     *      "/settlement/update/{objectId}/bounded/{objectClass}",
     *      name="settlement_update_bounded",
     *      host="{domain_dashboard}",
     *      defaults={"_locale" = "%locale%", "domain_dashboard" = "%domain_dashboard%"},
     *      requirements={"_locale" = "%locale%", "domain_dashboard" = "%domain_dashboard%", "objectId" = "\d+", "objectClass" = "[a-z]+"}
     * )
     */
    public function boundedAction($objectId, $objectClass)
    {
        $_manager = $this->getDoctrine()->getManager();

        $_translator = $this->get('translator');

        $_breadcrumbs = $this->get('app.common.breadcrumbs');

        $settlement = $_manager->getRepository('AppBundle:Settlement\Settlement')->find($objectId);

        if( !$settlement )
            throw $this->createNotFoundException("Settlement identified by `id` {$objectId} not found");

        if( !$this->isGranted(SettlementVoter::SETTLEMENT_READ, $settlement) )
            throw $this->createAccessDeniedException('Access denied');

        $_breadcrumbs->add('settlement_read')->add('settlement_update', ['id' => $objectId], $_translator->trans('settlement_bounded', [], 'routes'));

        switch(TRUE)
        {
            case $this->compareObjectClassNameToString(new School, $objectClass):
                $bounded = $this->forward('AppBundle:Binding\School:show', [
                    'objectClass' => $this->getObjectClassName($settlement),
                    'objectId'    => $objectId
                ]);

                $_breadcrumbs->add('settlement_update_bounded',
                    [
                        'objectId' => $objectId,
                        'objectClass' => $objectClass
                    ],
                    $_translator->trans('school_read', [], 'routes')
                );
            break;

            default:
                throw new NotAcceptableHttpException("Object not supported");
            break;
        }

        return $this->render('AppBundle:Entity/Settlement/Binding:bounded.html.twig', [
            'objectClass' => $objectClass,
            'bounded'     => $bounded->getContent(),
            'settlement'  => $settlement
        ]);
    }

    /**
     * @Method({"GET"})
     * @Route(
     *      "/settlement/choose_for/{objectClass}/{objectId}",
     *      name="settlement_choose",
     *      host="{domain_dashboard}",
     *      defaults={"_locale" = "%locale%", "domain_dashboard" = "%domain_dashboard%"},
     *      requirements={"_locale" = "%locale%", "domain_dashboard" = "%domain_dashboard%", "objectClass" = "[a-z]+", "objectId" = "\d+"}
     * )
     */
    public function chooseAction($objectClass, $objectId)
    {
        $_regionBoundlessAccess = $this->get('app.security.settlement_boundless_access');

        if( !$_regionBoundlessAccess->isGranted(SettlementBoundlessAccess::SETTLEMENT_BIND) )
            throw $this->createAccessDeniedException('Access denied');

        $_manager = $this->getDoctrine()->getManager();

        $_translator = $this->get('translator');

        $_breadcrumbs = $this->get('app.common.breadcrumbs');

        switch(TRUE)
        {
            case $this->compareObjectClassNameToString(new Region, $objectClass):
                $region = $object = $_manager->getRepository('AppBundle:Region\Region')->find($objectId);

                if( !$region )
                    throw $this->createNotFoundException("Region identified by `id` {$objectId} not found");

                $path = 'region_update_bounded';

                $_breadcrumbs->add('region_read')->add('region_update', ['id' => $objectId])->add('region_update_bounded',
                    [
                        'objectId'    => $objectId,
                        'objectClass' => 'settlement'
                    ],
                    $_translator->trans('settlement_read', [], 'routes')
                );
            break;

            default:
                throw new NotAcceptableHttpException("Object not supported");
            break;
        }

        $settlements = $_manager->getRepository('AppBundle:Settlement\Settlement')->findAll();

        $_breadcrumbs->add('settlement_choose', [
            'objectId'    => $objectId,
            'objectClass' => $objectClass
        ]);

        return $this->render('AppBundle:Entity/Settlement/Binding:choose.html.twig', [
            'path'        => $path,
            'settlements' => $settlements,
            'object'      => $object
        ]);
    }

    /**
     * @Method({"GET"})
     * @Route(
     *      "/settlement/bind/{targetId}/{objectClass}/{objectId}",
     *      name="settlement_bind",
     *      host="{domain_dashboard}",
     *      defaults={"_locale" = "%locale%", "domain_dashboard" = "%domain_dashboard%"},
     *      requirements={"_locale" = "%locale%", "domain_dashboard" = "%domain_dashboard%", "targetId" = "\d+", "objectClass" = "[a-z]+", "objectId" = "\d+"}
     * )
     */
    public function bindToAction(Request $request, $targetId, $objectClass, $objectId)
    {
        $_manager = $this->getDoctrine()->getManager();

        $_translator = $this->get('translator');

        $settlement = $_manager->getRepository('AppBundle:Settlement\Settlement')->find($targetId);

        if( !$settlement )
            throw $this->createNotFoundException($_translator->trans('common.error.not_found', [], 'responses'));

        if( !$this->isGranted(SettlementVoter::SETTLEMENT_BIND, $settlement) )
            throw $this->createAccessDeniedException($_translator->trans('common.error.forbidden', [], 'responses'));

        switch(TRUE)
        {
            case $this->compareObjectClassNameToString(new Region, $objectClass):
                $region = $_manager->getRepository('AppBundle:Region\Region')->find($objectId);

                if( !$region )
                    throw $this->createNotFoundException($_translator->trans('common.error.not_found', [], 'responses'));

                $region->addSettlement($settlement);

                $_manager->persist($region);
            break;

            default:
                throw new NotAcceptableHttpException($_translator->trans('bind.error.not_boundalbe', [], 'responses'));
            break;
        }

        $_manager->flush();

        return new RedirectResponse($request->headers->get('referer'));
    }

    /**
     * @Method({"GET"})
     * @Route(
     *      "/settlement/unbind/{targetId}/{objectClass}/{objectId}",
     *      name="settlement_unbind",
     *      host="{domain_dashboard}",
     *      defaults={"_locale" = "%locale%", "domain_dashboard" = "%domain_dashboard%"},
     *      requirements={"_locale" = "%locale%", "domain_dashboard" = "%domain_dashboard%", "targetId" = "\d+", "objectClass" = "[a-z]+", "objectId" = "\d+"}
     * )
     */
    public function unbindFromAction(Request $request, $targetId, $objectClass, $objectId)
    {
        $_manager = $this->getDoctrine()->getManager();

        $_translator = $this->get('translator');

        $settlement = $_manager->getRepository('AppBundle:Settlement\Settlement')->find($targetId);

        if( !$settlement )
            throw $this->createNotFoundException($_translator->trans('common.error.not_found', [], 'responses'));

        if( !$this->isGranted(SettlementVoter::SETTLEMENT_BIND, $settlement) )
            throw $this->createAccessDeniedException($_translator->trans('common.error.forbidden', [], 'responses'));

        switch(TRUE)
        {
            case $this->compareObjectClassNameToString(new Region, $objectClass):
                $settlement->setRegion(NULL);
            break;

            default:
                throw new NotAcceptableHttpException($_translator->trans('bind.error.not_unboundalbe', [], 'responses'));
            break;
        }

        $_manager->flush();

        return new RedirectResponse($request->headers->get('referer'));
    }
}