<?php

namespace App;

use App\Traits\UuidModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\ExtendedClasses\Relations\BelongsToMany;
use Psy\Exception\FatalErrorException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

abstract class BaseModel extends Model {
    use UuidModel, SoftDeletes;

    /**
     * Returns an array of related models for which this model should be retrieved 'with'.
     *
     * Will be used as argument to Model::with() when retrieving.
     */
    public static function getRetrieveWith()
    {
        return [];
    }

    /**
     * @var $PARENT_MODELS
     *
     * An array of mappings for parent resources. Used to define which entities this entity can be accessed from, and
     * how to get from those parent entities to this entity. See usage in RestResourceController::getAllForParent().
     *
     * Format:
     * [
     *      [parent_resource] => [
     *          'class' => [Model class],
     *          'function' => [Relationship function],
     *      ],
     * ]
     *
     * Where [parent_resource] is the resource as it would appear in a route (eg 'clients' for the Client model),
     * [Model class] is the fully qualified class name of the parent model class, and [Relationship function] is the
     * function used to get child records from this entity via the parent entity.
     *
     * E.g. If a user belongsToMany clients, the $PARENT_MODELS property in the user model would be:
     *
     * [
     *      'clients' => [
     *          'class' => 'App\Client',
     *          'function' => 'users',
     *      ],
     * ]
     */
    public static $PARENT_MODELS = [];

    /**
     * @var $unconventionalForeignKeys
     *
     * An array of fields for which uuid's should be converted to database id's, and vice versa.
     * 
     * Note that the sub-array key should be the field name to convert, with the value being an array
     * with the following elements:
     * 
     * 'table': the database table this is a foreign key for
     * 'newFieldName': the field name to rename this field to
     * 'addToHidden': true/false: if true, this field will not be returned in response body, but will remain in the entity.
     *                            if false, the converted field will be removed from the entity completely.
     *
     * Format: [
     *      'foreign_table_id' => [
     *          'table' => 'database_table_name',
     *          'newFieldName' => 'foreign_table_uuid,
     *          'addToHidden' => true,
     *      ],
     *      'foreign_table_uuid' => [
     *          'table' => 'database_table_name',
     *          'newFieldName' => 'foreign_table_id,
     *          'addToHidden' => false,
     *      ],
     * ]
     *
     * Note that any fields that match the convention database_table_name_id need not be added, they will automatically be added.
     */
    protected $unconventionalForeignKeys;

    /**
     * @var $conventionalNonForeignKeys
     *
     * An array of field names of fields that should NOT have their values converted from uuid's to database id's, regardless
     * of the fact that the field name follows the convention database_table_name_id.
     *
     * Note that any fields that do not match the aforementioned convention need not be added, as they will automatically not
     * be included in the list of fields to convert.
     */
    protected $conventionalNonForeignKeys;

    public static $FOREIGN_SORT_BY_OPTIONS = [];

    public function getUnconventionalForeignKeys()
    {
        return $this->unconventionalForeignKeys;
    }

    public function getConventionalNonForeignKeys()
    {
        return $this->conventionalNonForeignKeys;
    }

    /**
     * Inserts related models - by default, no associated models will be inserted. Child models that wish to allow
     * insertion of related models should override this function to perform the insertion
     *
     * @param $input - the request input for the given request.
     * @param $uuid - the uuid of the model that has been inserted
     * @return bool - true if successful, false otherwise
     */
    public function insertAssociatedData($input, $uuid = null)
    {
        return true;
    }

    /**
     * Deletes related models - by default, no associated models will be deleted. Child models that wish to allow
     * deletion of related models should override this function to perform the deletion
     *
     * @return bool - true if successful, false otherwise
     */
    public function deleteAssociatedData()
    {
        return true;
    }
    
    /**
     * Override the inbuilt belongsToMany function to return an extended 
     * BelongsToMany class.
     * 
     * @param  string  $related
     * @param  string  $table
     * @param  string  $foreignKey
     * @param  string  $otherKey
     * @param  string  $relation
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function belongsToMany($related, $table = null, $foreignKey = null, $otherKey = null, $relation = null)
    {
        // If no relationship name was passed, we will pull backtraces to get the
        // name of the calling function. We will use that function name as the
        // title of this relation since that is a great convention to apply.
        if (is_null($relation)) {
            $relation = $this->getBelongsToManyCaller();
        }

        // First, we'll need to determine the foreign key and "other key" for the
        // relationship. Once we have determined the keys we'll make the query
        // instances as well as the relationship instances we need for this.
        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $instance = new $related;

        $otherKey = $otherKey ?: $instance->getForeignKey();

        // If no table name was provided, we can guess it by concatenating the two
        // models using underscores in alphabetical order. The two model names
        // are transformed to snake case from their default CamelCase also.
        if (is_null($table)) {
            $table = $this->joiningTable($related);
        }

        // Now we're ready to create a new query builder for the related model and
        // the relationship instances for the relation. The relations will set
        // appropriate query constraint and entirely manages the hydrations.
        $query = $instance->newQuery();

        return new BelongsToMany($query, $this, $table, $foreignKey, $otherKey, $relation);
    }

    /**
     * Function called before calling attach() on any model extending this model
     *
     * For example, if calling $client->attach($userId), this function would be
     * called on the user model, with the param $id being $userId
     *
     * @param $parent - an instance of the model to which the child is being attached
     * @param $pivot_table string - the name of the pivot table
     * @param $attributes - an array of attributes to set on the new pivot table record
     * @param $id - the id of the related model being attached
     * @return array - the updated array of attributes to be set in the new pivot table record
     */
    public function setAttributesBeforeAttach($parent, $pivot_table, $attributes, $id)
    {
        return $attributes;
    }
    
    /**
     * Function called after calling attach() on any model extending this model
     * 
     * For example, if calling $client->attach($userId), this function would be
     * called on the user model, with the param $id being $userId
     * 
     * @param type $id - the param that was passed to $parentModel->attach()
     * @param string $parentClass - the class of the model this model was attached to
     * @return boolean - true on success, false otherwise
     */
    public function afterAttach($id, $parentClass)
    {
        return true;
    }
    
    /**
     * Given a foreign key field name, gets the uuid version of the foreign key name.
     * 
     * For conventional foreign keys:
     * if $foreignKey is client_group_id, this function will return client_group_uuid.
     * 
     * For unconventional foreign keys:
     * this function will check $unconventionalForeignKeys for $foreignKey, and return
     * $unconventionalForeignKeys[$foreignKey]['newFieldName']
     * 
     * @throws FatalErrorException if $foreignKey could not be recognised
     * @param string $foreignKey - the field name of the foreign key
     * @return string the field name of the uuid version of the foreign key
     */
    public function getUuidForeignKeyFieldName($foreignKey)
    {
        if (preg_match(UuidModel::$FOREIGN_KEY_REGEX_ID, $foreignKey)) {
            return preg_replace('/_id$/', '_uuid', $foreignKey);
        } else if (!empty($this->unconventionalForeignKeys[$foreignKey]['newFieldName'])) {
            return $this->unconventionalForeignKeys[$foreignKey]['newFieldName'];
        } else {
            throw new FatalErrorException('Foreign key not recorgnised via regex or unconventional foreign keys');
        }
    }

    public static function getIdByUuid($uuid)
    {
        $entity = static::uuid($uuid);
        return $entity->id;
    }

    public static function paginateModel($relationObject, $input)
    {
        if (!empty($input['sort_by'])) {
            $sort_by = $input['sort_by'];
            if (!empty($sort_by) && !Schema::hasColumn(static::getTableName(), $sort_by) && !in_array($sort_by, static::$FOREIGN_SORT_BY_OPTIONS)) {
                throw new BadRequestHttpException('The field ' . $sort_by . ' does not exist');
            }
        }

        $relationObject = $relationObject->with(static::getRetrieveWith());
        $relationObject = static::filter($relationObject, $input);

        if (!empty($input['sort_by'])) {
            $updatedValues = static::processSortByForRelatedModels($relationObject, $input['sort_by']);
            $relationObject = $updatedValues['relationObject'];
            $input['sort_by'] = $updatedValues['sortBy'];

            if (!empty($input['sort_type'])) {
                $relationObject = $relationObject->orderBy($input['sort_by'], $input['sort_type']);
            } else {
                $relationObject = $relationObject->orderBy($input['sort_by']);
            }
        }

        if (!empty($input['per_page'])) {
            $paginator = $relationObject->paginate($input['per_page']);
            $total = $paginator->total();
            $items = $paginator->getCollection();
        } else if ($relationObject instanceof EloquentBuilder || $relationObject instanceof QueryBuilder || $relationObject instanceof Relation) {
            $items = $relationObject->get();
            $total = $items->count();
        } else {
            $items = $relationObject->all();
            $total = $items->count();
        }

        return compact('items', 'total');
    }

    public static function filter($relationObject, $input) {
        return $relationObject;
    }

    public static function processSortByForRelatedModels($relationObject, $sortBy)
    {
        return compact('relationObject', 'sortBy');
    }

    public static function getTableName()
    {
        return with(new static)->getTable();
    }

    /**
     * Given the passed request data, determines what pivot table data needs to be set.
     *
     * For example, when creating a client or customer user, given that "role_uuid" is set in the request data, a
     * pivot table column client_role_id or customer_role_id should be set.
     *
     * @param $requestData - the request data sent to the API
     * @param string $parent - the string representation of the parent resource to which this is being attached eg 'clients' or 'customers'
     * @return array - the data to insert. Will be passed as second parameter to attach() function.
     */
    public static function preparePivotData($requestData, $parent = null)
    {
        return [];
    }
}