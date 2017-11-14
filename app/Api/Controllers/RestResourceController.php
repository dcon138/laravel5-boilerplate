<?php

namespace Api\Controllers;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Container\Container;
use Psy\Exception\FatalErrorException;
use App\BaseModel;
use Illuminate\Support\Facades\Route;
use Rhumsaa\Uuid\Console\Exception;
use Illuminate\Support\Facades\DB;
use App\Traits\UuidModel;
use Dingo\Api\Exception\ValidationHttpException;

abstract class RestResourceController extends BaseController {
    protected $modelClass;
    protected $requestClasses;

    /**
     * RestResourceController constructor. Checks that modelClass for the resource has been defined correctly.
     *
     * This constructor should be called as the last line of the constructor for any child controller(s).
     */
    public function __construct()
    {
        if (empty($this->modelClass)) {
            throw new FatalErrorException('Child Model class must be defined in Child Controller');
        } else if (!is_subclass_of($this->modelClass, BaseModel::class)) {
            throw new FatalErrorException('Child Model provided does not extend base model class');
        }
    }

    /**
     * Create a new entity
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(Request $request)
    {
        try {
            $this->validateChildFormRequest($request, 'create');
            $model = $this->modelClass;

            $entity = new $model;
            $entity->fill($request->input());

            $this->createEntityAndAssociatedData($request, $entity);
            return response()->json($this->generateSaveEntityResponse($model, $entity), 201);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        } catch (FatalErrorException $e) {
            return response()->json(['error' => 'An internal error has occurred'], 500);
        }
    }

    /**
     * Retrieves one record of a given entity by it's uuid
     *
     * @param Request $request - the request object
     * @param $uuid - the uuid of the entity to retrieve
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOne(Request $request, $uuid)
    {
        try {
            $model = $this->modelClass;
            $entity = $model::with($model::getRetrieveWith())->uuid($uuid);
            return response()->json($entity->toArray());
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Record not found'], 404);
        }
    }

    public function getOneBelongingToParent(Request $request, $parent, $parent_uuid)
    {
        try {
            $model = $this->modelClass;
            $parent = $this->getParentModelDetails($model, $parent, $parent_uuid);
            extract($parent);

            $entity = $parentObject->{$relationFunction}()->with($model::getRetrieveWith())->getResults();
            return response()->json($entity->toArray());
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Record not found'], 404);
        } catch (FatalErrorException $e) {
            return response()->json(['error' => 'An internal error has occurred'], 500);
        }
    }

    /**
     * Retrieves one record of a given entity by it's uuid,
     * then updates it with the data submitted in the request
     *
     * @param Request $request - the request object
     * @param $uuid - the uuid of the entity to update
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateOne(Request $request, $uuid)
    {
        try {
            $this->validateChildFormRequest($request, 'update');

            $model = $this->modelClass;
            $entity = $model::with($model::getRetrieveWith())->uuid($uuid);
            $entity->update($request->input());
            return response()->json($this->generateSaveEntityResponse($model, $entity));
        } catch (FatalErrorException $e) {
            return response()->json(['error' => 'An internal error has occurred'], 500);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Record not found'], 404);
        }
    }

    /**
     * Deletes one record of a given entity by it's uuid.
     *
     * @param \Illuminate\Http\Request $request - the request object
     * @param $uuid - the uuid of the entity to delete
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteOne(Request $request, $uuid)
    {
        try {
            $model = $this->modelClass;
            $entity = $model::uuid($uuid);

            try {
                $this->deleteEntityAndAssociatedData($entity);
                return response()->make('', 204);
            } catch (FatalErrorException $e) {
                return response()->json(['error' => 'Something went wrong whilst deleting the record.'], 500);
            }
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Record not found'], 404);
        }
    }

    /**
     * Given an existing row in a parent class, and an existing row in a child class, attach the child to the parent
     * via a many-to-many relationship.
     *
     * @param Request $request base form request class
     * @param $parent string - the string representation of the parent resource (from route)
     * @param $parent_uuid string - the uuid of the parent record to attach the child to
     * @return \Illuminate\Http\JsonResponse
     */
    public function attachExistingChildToParent(Request $request, $parent, $parent_uuid)
    {
        try {
            $model = $this->modelClass;
            $parentFromRoute = $parent;

            $parent = $this->getParentModelDetails($model, $parent, $parent_uuid);
            extract($parent);
            
            $indexOfLastSlash = strrpos($model, '\\');
            $modelNameNoSlashes = substr($model, ($indexOfLastSlash+1));
            $model_uuid = strtolower($modelNameNoSlashes) . '_uuid';
            $entity = $model::uuid($request->input($model_uuid));

            //determine if there is any pivot data to insert
            $pivotFields = $model::preparePivotData($request->input(), $parentFromRoute);

            $parentObject->{$relationFunction}()->attach($entity->id, $pivotFields);

            return response()->json($this->generateSaveEntityResponse($model, $entity, $parentObject, $relationFunction), 201);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Record not found'], 404);
        } catch (FatalErrorException $e) {
            return response()->json(['error' => 'An internal error has occurred'], 500);
        }
    }

    /**
     * Creates a child resource underneath a parent resource, and associates it to that resource (many-to-many association)
     *
     * @param Request $request - the request object
     * @param $parent - the parent entity resource (from route)
     * @param $parent_uuid - the parent entity uuid (from route)
     * @return \Illuminate\Http\JsonResponse
     */
    public function createOneUnderParent(Request $request, $parent, $parent_uuid)
    {
        try {
            $this->validateChildFormRequest($request, 'create');
            $model = $this->modelClass;
            $parentFromRoute = $parent;

            $parent = $this->getParentModelDetails($model, $parent, $parent_uuid);
            extract($parent);

            $entity = new $model;
            $entity->fill($request->input());

            $this->createEntityAndAssociatedData($request, $entity);

            //determine if there is any pivot data to insert
            $pivotFields = $model::preparePivotData($request->input(), $parentFromRoute);

            $parentObject->{$relationFunction}()->attach($entity->id, $pivotFields);

            return response()->json($this->generateSaveEntityResponse($model, $entity, $parentObject, $relationFunction), 201);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Record not found'], 404);
        } catch (FatalErrorException $e) {
            return response()->json(['error' => 'An internal error has occurred'], 500);
        }
    }

    /**
     * Create a child resource underneath a parent resource, with parent resource foreign key populated (belongsTo association)
     *
     * @param Request $request - the request object
     * @param $parent - the parent entity resource (from route)
     * @param $parent_uuid - the parent entity uuid (from route)
     * @param $returnUuid - if true, the uuid will be returned, if false, the API response will be returned
     * @param $replaceInputWith - see validateChildFormRequest's docs for description of this
     * @throws ModelNotFoundException
     * @throws FatalErrorException
     * @return \Illuminate\Http\JsonResponse
     */
    public function createOneBelongingToParent(Request $request, $parent, $parent_uuid, $returnUuid = false, $replaceInputWith = false)
    {
        try {
            if ($returnUuid) {
                $result = $this->validateChildFormRequest($request, 'create', $replaceInputWith, true);
                if ($result !== true) {
                    return $result;
                }
            } else {
                $this->validateChildFormRequest($request, 'create', $replaceInputWith);
            }

            $model = $this->modelClass;

            $parent = $this->getParentModelDetails($model, $parent, $parent_uuid);
            extract($parent);

            $foreignKey = $parentObject->{$relationFunction}()->getPlainForeignKey();
            
            $entity = new $model;
            $entity->fill($request->input());
            
            $uuidForeignKey = $entity->getUuidForeignKeyFieldName($foreignKey);
            $entity->{$uuidForeignKey} = $parent_uuid;

            $this->createEntityAndAssociatedData($request, $entity);

            if ($returnUuid) {
                return $entity->uuid;
            } else {
                return response()->json($this->generateSaveEntityResponse($model, $entity), 201);
            }
        } catch (ModelNotFoundException $e) {
            if ($returnUuid) {
                throw $e;
            } else {
                return response()->json(['error' => 'Record not found'], 404);
            }
        } catch (FatalErrorException $e) {
            if ($returnUuid) {
                throw $e;
            } else {
                return response()->json(['error' => $e->getMessage()], 500);
            }
        }
    }

    /**
     * Retrieves one record of a given entity by it's uuid,
     * then updates it with the data submitted in the request.
     *
     * Additionally validates that the resource belongs to a specific parent resource
     *
     * @param Request $request - the request object
     * @param $parent - the parent entity resource (from route)
     * @param $parent_uuid - the parent entity uuid (from route)
     * @param $uuid - the uuid of the entity to update
     * @param $returnSuccess - if true, a success flag will be returned, if false the API response will be returned.
     * @param $replaceInputWith - see validateChildFormRequest's docs for description of this
     * @throws FatalErrorException
     * @throws ModelNotFoundException
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateOneUnderParent(Request $request, $parent, $parent_uuid, $uuid, $returnSuccess = false, $replaceInputWith = false)
    {
        try {
            if ($returnSuccess) {
                $result = $this->validateChildFormRequest($request, 'update', $replaceInputWith, true);
                if ($result !== true) {
                    return $result;
                }
            } else {
                $this->validateChildFormRequest($request, 'update', $replaceInputWith);
            }

            $model = $this->modelClass;

            //get parent model details
            $parent = $this->getParentModelDetails($model, $parent, $parent_uuid);
            extract($parent);

            //retrieve the child entity
            $entity = $model::with($model::getRetrieveWith())->uuid($uuid);

            //if the parent and child entity are not related, throw a 404
            if (!$parentObject->{$relationFunction}()->where('uuid', $uuid)->exists()) {
                throw new ModelNotFoundException('Parent and child entity do not appear to be linked.');
            }
            $entity->update($request->input());
            
            if ($returnSuccess) {
                return true;
            } else {
                return response()->json($this->generateSaveEntityResponse($model, $entity, $parentObject, $relationFunction));
            }
        } catch (FatalErrorException $e) {
            if ($returnSuccess) {
                throw $e;
            } else {
                return response()->json(['error' => 'An internal error has occurred'], 500);
            }
        } catch (ModelNotFoundException $e) {
            if ($returnSuccess) {
                throw $e;
            } else {
                return response()->json(['error' => 'Record not found'], 404);
            }
        }
    }

    /**
     * Retrieves all entities that belong to one record of a parent entity, based on the uuid of the parent entity.
     *
     * @param Request $request - the request object
     * @param $parent - the parent entity resource (from route)
     * @param $parent_uuid - the parent entity uuid (from route)
     * @param $includeTrashed - if true, will include soft deleted records. If false, will not.
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllForParent(Request $request, $parent, $parent_uuid, $includeTrashed = false)
    {
        try {
            $model = $this->modelClass;

            $parent = $this->getParentModelDetails($model, $parent, $parent_uuid);
            extract($parent);

            //retrieve child models, and return response.
            $relationObject = $parentObject->{$relationFunction}();

            if ($includeTrashed === true) {
                $relationObject = $relationObject->withTrashed();
            }

            $children = $relationObject->with($model::getRetrieveWith())->get();
            return response()->json($children->toArray());
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Record not found'], 404);
        } catch (FatalErrorException $e) {
            return response()->json(['error' => 'An internal error has occurred'], 500);
        }
    }
    
    public function getAllNotAssociatedToParent(Request $request, $parent, $parent_uuid)
    {
        try {
            $model = $this->modelClass;
            
            $parent = $this->getParentModelDetails($model, $parent, $parent_uuid);
            extract($parent);
            
            $attachedIds = $parentObject->{$relationFunction}()->getRelatedIds();
            
            $results = $model::with($model::getRetrieveWith())->whereNotIn('id', $attachedIds)->get();
            
            return response()->json($results->toArray());
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Record not found'], 404);
        } catch (FatalErrorException $e) {
            return response()->json(['error' => 'An internal error has occurred'], 500);
        }
    }

    /**
     * Unassociates child resources from a parent resource (associated by many-to-many).
     *
     * Accepts an array of uuid's as the request body
     *
     * @param Request $request - the request object
     * @param $parent - the parent entity resource (from route)
     * @param $parent_uuid - the parent entity uuid (From route)
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     */
    public function detachChildrenFromParent(Request $request, $parent, $parent_uuid)
    {
        try {
            $model = $this->modelClass;

            $uuidsToDelete = $request->input();

            $entityIds = $model::uuid($uuidsToDelete, false)->lists('id');

            if (count(array_unique($uuidsToDelete)) !== $entityIds->count()) {
                throw new ModelNotFoundException('One or more records were not found');
            }

            $parent = $this->getParentModelDetails($model, $parent, $parent_uuid);
            extract($parent);

            $parentObject->{$relationFunction}()->detach($entityIds);

            return response()->make('', 204);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Record not found'], 404);
        } catch (FatalErrorException $e) {
            return response()->json(['error' => 'An internal error has occurred'], 500);
        }
    }

    /**
     * Deletes any number of child resources belonging to a parent resource (associated by child belongsTo parent).
     *
     * Accepts an array of uuid's as the request body
     *
     * @param Request $request - the request object
     * @param $parent - the parent entity resource (from route)
     * @param $parent_uuid - the parent entity uuid (From route)
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     */
    public function deleteChildrenBelongingToParent(Request $request, $parent, $parent_uuid)
    {
        try {
            $model = $this->modelClass;

            $uuidsToDelete = $request->input();

            //get parent details
            $parent = $this->getParentModelDetails($model, $parent, $parent_uuid);
            extract($parent);

            $children = $parentObject->{$relationFunction}()->whereIn('uuid', $uuidsToDelete)->get();

            //confirm all records are found and belong to that parent
            if (count(array_unique($uuidsToDelete)) !== $children->count()) {
                throw new ModelNotFoundException('One or more records were not found');
            }

            $children->each(function ($item, $key) {
                $item->delete();
            });

            return response()->make('', 204);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Record not found'], 404);
        } catch (FatalErrorException $e) {
            return response()->json(['error' => 'An internal error has occurred'], 500);
        }
    }

    /**
     * Retrieves all records for a given entity.
     *
     * @param \Illuminate\Http\Request $request - the request object
     * @param array $conditions - an array of conditions to apply. 
     *                          eg [['field'=>'name', 'operator'=>'=','value'=>'daniel'],]
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAll(Request $request, $conditions = [])
    {
        try {
            $model = $this->modelClass;
            $queryObj = $model::with($model::getRetrieveWith());
            
            $queryObj = $this->applyQueryConditions($queryObj, $conditions);
            
            $results = $queryObj->get();
            return response()->json($results->toArray(), 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Model not found'], 404);
        }
    }

    /**
     * HEAD request to check if an entity exists based on the value of a single field.
     *
     * Will return 200 if entity exists, 404 if it doesn't, and 500 if anything goes wrong (no error message as HEAD
     * request cannot have a body).
     *
     * @param Request $request - the request object
     * @param $fieldValue - the value of the field for which we are checking
     * @param $field - the name of the field for which we are checking
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\JsonResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function checkEntityByField(Request $request, $fieldValue, $field)
    {
        $this->denyGetHttpMethod($request);

        $statusCode = 500;
        try {
            $model = $this->modelClass;


            if ($model::where($field, '=', $fieldValue)->exists()) {
                $statusCode = 200;
            } else {
                $statusCode = 404;
            }
        } catch (\Exception $e) {
            //nothing to be done here, let it return 500
        }
        return response('', $statusCode);
    }

    /**
     * Attaches one or multiple entities to a parent entity belonging to a grandparent entity.
     *
     * @param Request $request - the request object
     * @param $grandparent - the grandparent entity
     * @param $grandparent_uuid - the uuid of the specific grandparent record
     * @param $parent - the parent entity
     * @param $parent_uuid - the uuid of the specific parent record
     * @return \Illuminate\Http\JsonResponse
     */
    public function attachMultipleToDepthTwoParent(Request $request, $grandparent, $grandparent_uuid, $parent, $parent_uuid)
    {
        try {
            $model = $this->modelClass;

            $uuidsToAttach = $request->input();

            //get parent details
            $parentDetails = $this->getParentModelDetails($model, $parent, $parent_uuid);
            extract($parentDetails);

            $grandparentDetails = $this->getParentModelDetails($parentClass, $grandparent, $grandparent_uuid);

            //confirm parent belongs to grandparent
            if (!$grandparentDetails['parentObject']->{$grandparentDetails['relationFunction']}()->where('uuid', $parent_uuid)->exists()) {
                throw new ModelNotFoundException('Association between parent and grandparent was not found');
            }

            //retrieve current model ids with uuid IN $uuidsToAttach, and confirm that they are all found
            $entityIds = $model::whereIn('uuid', $uuidsToAttach)->lists('id')->toArray();

            if (count($entityIds) !== count(array_unique($uuidsToAttach))) {
                throw new ModelNotFoundException('One or more records were not found');
            }

            //attach child to the parent
            foreach ($entityIds as $entityId) {
                if (!$parentObject->{$relationFunction}->contains($entityId)) {
                    $parentObject->{$relationFunction}()->attach($entityId);
                }
            }

            return response()->json($this->generateSaveEntityResponse($parentClass, $parentObject), 201);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Record not found'], 404);
        } catch (FatalErrorException $e) {
            return response()->json(['error' => 'An internal error has occurred'], 500);
        }
    }
    
    public function syncChildrenBelongingToParent(Request $request, $parent, $parent_uuid)
    {
        if (!is_array($request->input())) {
            return response()->json(['error' => 'Request body must be an array of objects'], 400);
        }

        $errors = [];
        try {
            $model = $this->modelClass;

            $requestBody = $request->input();

            //get parent details
            $parentDetails = $this->getParentModelDetails($model, $parent, $parent_uuid);
            extract($parentDetails);

            //do all of this in a transaction
            DB::transaction(function () use ($model, $requestBody, $parent_uuid, $request, $parent, $parentObject, $relationFunction, &$errors) {

                //figure out which items to not delete
                $uuidsToNotDelete = [];
                foreach ($requestBody as $jsonObject) {
                    if (!empty($jsonObject['uuid'])) {
                        $uuidsToNotDelete[] = $jsonObject['uuid'];
                    }
                }

                //delete all items that aren't being kept. Do this before inserting/updating to prevent potential validation errors
                $itemIdsToDelete = $parentObject->{$relationFunction}()->whereNotIn('uuid', $uuidsToNotDelete)->lists('id')->toArray();
                $model::destroy($itemIdsToDelete);

                $hasErrors = false;
                foreach ($requestBody as $jsonObject) {
                    $request->replace($jsonObject);
                    if (!empty($jsonObject['uuid'])) {
                        //this is an update, run update child belonging to parent
                        $result = $this->updateOneUnderParent($request, $parent, $parent_uuid, $jsonObject['uuid'], true, $jsonObject);
                        $uuidsToNotDelete[] = $jsonObject['uuid'];
                        if ($result === true) {
                            //in this case, there were no validation errors
                            $errors = $this->addErrorToArray($errors, []);
                        } else {
                            //in this case, $result contains validation errors
                            $errors = $this->addErrorToArray($errors, $result);
                            $hasErrors = true;
                        }

                    } else {
                        //this is an insert, run create one belonging to parent
                        $new_uuid = $this->createOneBelongingToParent($request, $parent, $parent_uuid, true, $jsonObject);

                        if (UuidModel::valueLooksLikeUuid($new_uuid)) {
                            //insert was successful (no validation errors)
                            $uuidsToNotDelete[] = $new_uuid;
                            $errors = $this->addErrorToArray($errors, []);
                        } else {
                            //there were validation errors, now represented in $new_uuid
                            $errors = $this->addErrorToArray($errors, $new_uuid);
                            $hasErrors = true;
                        }
                    }
                }

                if ($hasErrors) {
                    //TODO replicate this in a better way: throw new PutValidationException();
                }
            });

            $responseBody = $parentObject->{$relationFunction}()->with($model::getRetrieveWith())->get();
            return response()->json($responseBody, 200);
//        } catch (PutValidationException $e) {
//            return response()->json($errors, 422);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Record not found'], 404);
        } catch (FatalErrorException $e) {
            return response()->json(['error' => 'An internal error has occurred'], 500);
        }
    }

    public function getPaginatedRecords(Request $request, $parent, $parent_uuid)
    {
        try {
            if (!empty($this->requestClasses['paginate'])) {
                $this->validateChildFormRequest($request, 'paginate');
            }

            $model = $this->modelClass;

            $relationObject = $this->getRelationObject($model, $parent, $parent_uuid);

            $details = $model::paginateModel($relationObject, $request->input());
            extract($details);

            return $this->generateGetPaginatedRecordsResponse($items, $total);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Record not found'], 404);
        } catch (FatalErrorException $e) {
            return response()->json(['error' => 'An internal error has occurred'], 500);
//        } catch (PutValidationException $e) {
//            return response()->json(['errors' => ['sort_by' => $e->getMessage()]], 422);
        }
    }

    private function addErrorToArray($errors, $error)
    {
        $errors[] = ['errors' => $error];
        return $errors;
    }

    /**
     * Validates the request according to validation and authorization rules defined in a child class of FormRequest.
     * This is necessary because we can't type-hint based on dynamic values set in child classes.
     *
     * @param $request - the current request object
     * @param $operation - the operation being performed on the current entity
     * @param $replaceInputWith - if false, parent request input will be validated. If not false, request input will be
     *                            replaced with the value of this param before validation
     * @param $returnErrors - if true, validation exception will be caught and error messages will be returned.
     *                        if false, validation exception will just be left uncaught
     * @throws FatalErrorException - if the child class of FormRequest hasn't been set into $this->requestClass in the
     *                               child controller.
     * @return bool|mixed - if $returnErrors is true, will return true on success, or return errors on failure
     *                      if $returnErrors is false, will not return anything on success, but exception will be thrown on failure.
     */
    protected function validateChildFormRequest($request, $operation, $replaceInputWith = false, $returnErrors = false)
    {
        if (empty($operation) || empty($this->requestClasses[$operation])) {
            throw new FatalErrorException('Child FormRequest class must be defined with validation and authorize rules');
        }
        $childRequestClass = $this->requestClasses[$operation];
        return $this->runChildFormRequestValidation($request, $childRequestClass, $replaceInputWith, $returnErrors);
    }

    /**
     * Instantiates and runs the child form request class (see validateChildFormRequest() from this file)
     *
     * @param $request - the current request object
     * @param $childRequestClass - the class of the child form request to run
     * @param $replaceInputWith - if false, parent request input will be validated. If not false, request input will be
     *                            replaced with the value of this param before validation
     * @param $returnErrors - if true, validation exception will be caught and error messages will be returned.
     *                        if false, validation exception will just be left uncaught
     * @return bool|mixed - if $returnErrors is true, will return true on success, or return errors on failure
     *                      if $returnErrors is false, will not return anything on success, but exception will be thrown on failure.
     */
    protected function runChildFormRequestValidation($request, $childRequestClass, $replaceInputWith = false, $returnErrors = false)
    {
        $newRequest = $childRequestClass::createFromBase($request);
        $newRequest->setRouteResolver($request->getRouteResolver());
        $newRequest->setContainer(Container::getInstance());
        if ($replaceInputWith !== false) {
            $newRequest->replace($replaceInputWith);
        }
        if ($returnErrors) {
            try {
                $newRequest->validate();
                return true;
            } catch(ValidationHttpException $e) {
                return $e->getErrors()->toArray();
            }
        } else {
            $newRequest->validate();
        }
    }

    /**
     * Reusable logic to determine parent model details and object for a given child resource and parent resource uuid.
     *
     * @param $model - the model class of the child resource
     * @param $parent - the parent model class name
     * @param $parent_uuid - the uuid of the parent model record
     * @return array - parent model class, relation function to get from parent to child model, parent model object
     * @throws FatalErrorException
     */
    protected function getParentModelDetails($model, $parent, $parent_uuid)
    {
        //get details for parent model, and how to retrieve child model from parent
        $parentModels = $model::$PARENT_MODELS;

        if (empty($parentModels[$parent]['class']) || empty($parentModels[$parent]['function'])) {
            throw new FatalErrorException('Parent model definition must include class and function name');
        }

        $parent = $parentModels[$parent];
        $parentClass = $parent['class'];
        $relationFunction = $parent['function'];

        //get the parent model
        $parentObject = $parentClass::uuid($parent_uuid);

        //ensure that the defined function exists to get from the parent model to the child model collection
        if (!method_exists($parentObject, $relationFunction)) {
            throw new FatalErrorException('Function ' . $relationFunction . ' did not exist on parent model class ' . $parentClass);
        }

        return compact('parentClass', 'relationFunction', 'parentObject');
    }

    /**
     * Wrapper function around logic to create an already populated entity, and also call function to insert any
     * associated data that may need to be added.
     *
     * @param $request - the request object
     * @param $entity - the entity to be saved
     */
    private function createEntityAndAssociatedData($request, $entity)
    {
        $entity_uuid = null;
        DB::transaction(function() use ($request, $entity, $entity_uuid) {
            //save the entity itself
            $entity->save();
            
            if (!empty($entity->uuid)) {
                $entity_uuid = $entity->uuid;
            }

            //save any related entities
            $entity->insertAssociatedData($request->input(), $entity_uuid);
        });
    }

    private function deleteEntityAndAssociatedData($entity)
    {
        DB::transaction(function() use ($entity) {
            $result1 = $entity->deleteAssociatedData();
            $result2 = $entity->delete();

            if (!$result1 || !$result2) {
                throw new FatalErrorException('An internal error has occurred');
            }
        });
    }

    protected function generateSaveEntityResponse($model, $entity, $parentObject = false, $relationFunction = false)
    {
        if ($parentObject !== false && $relationFunction !== false) {
            return $parentObject->{$relationFunction}()->with($model::getRetrieveWith())->find($entity->id)->toArray();
        } else {
            return $model::with($model::getRetrieveWith())->find($entity->id)->toArray();
        }
    }

    protected function denyGetHttpMethod($request) {
        if ($request->isMethod('get')) {
            //this endpoint is only able to be used with a HEAD request
            return response()->json(['error' => 'method not allowed'], 405);
        }
    }

    protected function generateGetPaginatedRecordsResponse($items, $total)
    {
        return response()->json($items->toArray(), 200, ['X-Total-Count' => $total]);
    }

    protected function getRelationObject($model, $parent, $parent_uuid)
    {
        $parentDetails = $this->getParentModelDetails($model, $parent, $parent_uuid);
        extract($parentDetails);
        return $parentObject->{$relationFunction}();
    }
    
    protected function applyQueryConditions($queryObj, $conditions)
    {
        if (!empty($conditions)) {
            foreach ($conditions as $condition) {
                if (!isset($condition['field']) || !isset($condition['operator']) || !isset($condition['value'])) {
                    throw new FatalErrorException('Conditions passed must have field, operator and value defined');
                }

                $queryObj = $queryObj->where($condition['field'], $condition['operator'], $condition['value']);
            }
        }
        return $queryObj;
    }
}