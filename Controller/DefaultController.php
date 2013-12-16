<?php

namespace Tutei\FormsBundle\Controller;

use eZ\Publish\Core\MVC\Symfony\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;

class DefaultController extends Controller {

    /* multiupload image example */
    public function indexAction($locationId) {        
        
        $request = $this->getRequest();
        
        $form = $this->container->get('form.factory')->createBuilder('form')
                ->add('files', 'file', array(                    

                        "attr" => 
                           array("accept" => "image/*", "multiple" => "multiple")
                            
                    ) )
                ->getForm();      
        
        
        $formView = $form->createView();
        $formView->children['files']->vars['full_name'] = 'form[files][]';   
        
        $form->handleRequest($request);

        if ( $request->isMethod('POST')) {
            $errors = $form->getErrors();
            
            //$form->bind($request);
            //var_dump($errors);exit;

            if ($form->isValid() or
                    ( count($errors) == 1 and 
                    $errors[0]->getMessageTemplate() == 'The CSRF token is invalid. Please try to resubmit the form.' )) {
                
                $data = $form->getData();
                
                $form_data = $request->request->get('form');
                $token = $form_data['ezxform_token'];
                
                $cacheDir = $this->container->getParameter('kernel.cache_dir');
                $upload_folder = $cacheDir . DIRECTORY_SEPARATOR . $token;
                
                if (!file_exists($upload_folder)) {
                    mkdir($upload_folder);
                }
                
                $files = array();
                foreach($data['files'] as $file){
                    //$file = $data['image'];
                    $file->move($upload_folder, $file->getClientOriginalName());
                    $path = $upload_folder
                        . DIRECTORY_SEPARATOR . $file->getClientOriginalName();

                    
                    $fields = array(
                        array('name'=>'name', 'value'=>$file->getClientOriginalName()),
                        array('name'=>'image', 'value'=>$path)
                    );
                    $files[]=$fields;

                }
                
                $upload_data = array('locationId' => $locationId,
                    'files' => $files);

                file_put_contents($upload_folder . DIRECTORY_SEPARATOR . 'data.json', json_encode($upload_data));
                
                $url = $this->container->get('router')->generate(
                        'tutei_forms_process', array('token' => $token));
                return new RedirectResponse($url);
            } else {
                $url = $this->container->get('router')->generate(
                        'tutei_forms_homepage', array('locationId' => $locationId));
                return new RedirectResponse($url);
                
            }
        }

        return $this->render('TuteiFormsBundle:Default:index.html.twig', array('form' => $formView,
                                                            'locationId'=>$locationId));
    }
    
    public function processAction($token) {
        $cacheDir = $this->container->getParameter('kernel.cache_dir');
        $upload_folder = $cacheDir . DIRECTORY_SEPARATOR . $token;
        $upload_data = json_decode(file_get_contents($upload_folder . '/data.json'));
        
        foreach($upload_data->files as $file){
            $this->createContent($upload_data->locationId, 'image', $file);
        }
        self::deleteDir($upload_folder);

        $url = $this->container->get('router')->generate(
                'tutei_forms_homepage', array('locationId' => $upload_data->locationId));

        return new RedirectResponse($url);
    }
    
    public static function deleteDir($dirPath) {
        if (!is_dir($dirPath)) {
            throw new InvalidArgumentException("$dirPath must be a directory");
        }
        if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
            $dirPath .= '/';
        }
        $files = glob($dirPath . '*', GLOB_MARK);
        foreach ($files as $file) {
            if (is_dir($file)) {
                self::deleteDir($file);
            } else {
                unlink($file);
            }
        }
        rmdir($dirPath);
    }



    public function createContent($locationId, $contentType, $fields) {
        $repository = $this->container->get('ezpublish.api.repository');
        $contentTypeService = $repository->getContentTypeService();
        $contentType = $contentTypeService->loadContentTypeByIdentifier($contentType);
        $contentService = $repository->getContentService();
        $contentCreateStruct = $contentService->newContentCreateStruct($contentType, 'eng-GB');
        

        foreach ($fields as $field) {
            $contentCreateStruct->setField($field->name, $field->value);
        }


        $locationService = $repository->getLocationService();

        $locationCreateStruct = $locationService->newLocationCreateStruct($locationId);

        $draft = $contentService->createContent($contentCreateStruct, array($locationCreateStruct));
        return $contentService->publishVersion($draft->versionInfo);
    }

}
