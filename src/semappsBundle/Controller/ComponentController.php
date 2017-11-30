<?php

namespace semappsBundle\Controller;


use Symfony\Component\HttpFoundation\Request;

class ComponentController extends AbstractMultipleComponentController
{
		public function getSFLoginOfCurrentUser()
		{
				return $this->getUser()->getEmail();
		}

		public function getSFPasswordOfCurrentUser()
		{
				/** @var \semappsBundle\Services\Encryption $encryption */
				$encryption 	= $this->container->get('semappsBundle.encryption');
				return $encryption->decrypt($this->getUser()->getSfUser());

		}

		public function getGraphOfCurrentUser()
		{
        $organisationEntity = $this->getDoctrine()->getManager()->getRepository(
          'semappsBundle:Organisation'
        );
        $organisation = $organisationEntity->find(
          $this->getUser()->getFkOrganisation()
        );
				$graphURI = $organisation->getGraphURI();

				return $graphURI;
		}


		public function addAction($componentName,Request $request)
		{
				/** @var \VirtualAssembly\SparqlBundle\Services\SparqlClient $sparqlClient */
				$sparqlClient = $this->container->get('sparqlbundle.client');
				$uri 					= $request->get('uri');
				$graphURI			= $this->getGraphOfCurrentUser();
				$sfClient       = $this->container->get('semantic_forms.client');
				$form 				= $this->getSfForm($sfClient,$componentName, $request);

				// Remove old picture.
				$fileUploader = $this->get('semappsBundle.fileUploader');
				$pictureDir = $fileUploader->getTargetDir();
				//actualPicture
				$sparql = $sparqlClient->newQuery($sparqlClient::SPARQL_SELECT);
				$sparql->addPrefixes($sparql->prefixes)
					->addPrefix('pair', 'http://virtual-assembly.org/pair#')
					->addSelect('?oldImage')
					->addWhere(
						$sparql->formatValue($uri, $sparql::VALUE_TYPE_URL),
						'pair:image',
						'?oldImage',
						$sparql->formatValue($graphURI, $sparql::VALUE_TYPE_URL));
				$results = $sfClient->sparql($sparql->getQuery());
				$actualImage = $sfClient->sparqlResultsValues($results);
				$actualImageName = null;
				if (!empty($actualImage)) {
						$cutUrl = explode("/", $actualImage[0]['oldImage']);
						$actualImageName = $cutUrl[sizeof($cutUrl) - 1];
				}
				$form->handleRequest($request);
				if ($form->isSubmitted() && $form->isValid()) {
						// Manage picture.
						if($form->has('componentPicture')){
								$newPicture = $form->get('componentPicture')->getData();
								if ($newPicture) {

										if ($actualImageName) {
												// Check if file exists to avoid all errors.
												if (is_file($pictureDir . '/' . $actualImageName)) {
														$fileUploader->remove($actualImageName);
												}
										}
										$newPictureName = $fileUploader->upload($newPicture);

										$sparql = $sparqlClient->newQuery($sparqlClient::SPARQL_DELETE);
										$sparql->addPrefixes($sparql->prefixes)
											->addPrefix('pair', 'http://virtual-assembly.org/pair#')
											->addDelete(
												$sparql->formatValue($uri, $sparql::VALUE_TYPE_URL),
												'pair:image',
												'?o',
												$sparql->formatValue($graphURI, $sparql::VALUE_TYPE_URL))
											->addWhere(
												$sparql->formatValue($uri, $sparql::VALUE_TYPE_URL),
												'pair:image',
												'?o',
												$sparql->formatValue($graphURI, $sparql::VALUE_TYPE_URL));
										$sfClient->update($sparql->getQuery());

										$sparql = $sparqlClient->newQuery($sparqlClient::SPARQL_INSERT_DATA);
										$sparql->addPrefixes($sparql->prefixes)
											->addPrefix('pair', 'http://virtual-assembly.org/pair#')
											->addInsert(
												$sparql->formatValue($uri, $sparql::VALUE_TYPE_URL),
												'pair:image',
												$sparql->formatValue($fileUploader->generateUrlForFile($newPictureName), $sparql::VALUE_TYPE_TEXT),
												$sparql->formatValue($graphURI, $sparql::VALUE_TYPE_URL));
										$sfClient->update($sparql->getQuery());
								}
						}
						$this->addFlash('info', 'Le contenu à bien été mis à jour.');
						return $this->redirectToRoute(
							'componentList', ["componentName" => $componentName]
						);
				}
				// Fill form
				return $this->render(
					'semappsBundle:Component:'.$componentName.'Form.html.twig',
					['image' => $actualImageName,
						'form' => $form->createView()
					]
				);

		}

	}
