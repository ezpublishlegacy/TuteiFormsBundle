<?php

namespace Tutei\FormsBundle\Controller;

use eZ\Publish\Core\MVC\Symfony\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;

class DefaultController extends Controller {

    public function indexAction($locationId) {        
        
        $request = $this->getRequest();
   
        $form = $this->container->get('form.factory')->createBuilder('form')
                ->add('name', 'text')
                ->add('image', 'file')
                ->getForm();
        

        $form->handleRequest($request);

        if ( $request->isMethod('POST')) {
            
            //$form->bind($request);

            if ($form->isValid()) {
                $data = $form->getData();

                $file = $data['image'];
                $file->move($this->container->getParameter('kernel.cache_dir'), $file->getClientOriginalName());
                $path = $this->container->getParameter('kernel.cache_dir')
                        . '/' . $file->getClientOriginalName();

                
                $fields = array(
                    array('name'=>'name', 'value'=>$data['name']),
                    array('name'=>'image', 'value'=>$path)
                );
                
                $this->createContent($locationId, 'image', $fields);
                
                unlink($path);
                
                $url = $this->container->get('router')->generate(
                                'tutei_forms_homepage', 
                                array('locationId'=> $locationId ));
                return new RedirectResponse( $url );
            } else {
                $url = $this->container->get('router')->generate(
                                'tutei_forms_homepage', 
                                array('locationId'=> $locationId ));
                return new RedirectResponse($url);
                
            }
        }

        return $this->render('TuteiFormsBundle:Default:index.html.twig', array('form' => $form->createView(),
                                                            'locationId'=>$locationId));
    }

    public function createContent($locationId, $contentType, $fields) {
        $repository = $this->container->get('ezpublish.api.repository');
        $contentTypeService = $repository->getContentTypeService();
        $contentType = $contentTypeService->loadContentTypeByIdentifier($contentType);
        $contentService = $repository->getContentService();
        $contentCreateStruct = $contentService->newContentCreateStruct($contentType, 'eng-GB');
        

        foreach ($fields as $field) {
            $contentCreateStruct->setField($field['name'], $field['value']);
        }


        $locationService = $repository->getLocationService();

        $locationCreateStruct = $locationService->newLocationCreateStruct($locationId);

        $draft = $contentService->createContent($contentCreateStruct, array($locationCreateStruct));
        return $contentService->publishVersion($draft->versionInfo);
    }

}
