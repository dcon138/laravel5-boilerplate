<?php

namespace App\ExtendedClasses\Relations;

use Illuminate\Database\Eloquent\Relations\BelongsToMany as ParentBelongsToMany;
use Psy\Exception\FatalErrorException;
use Illuminate\Database\Eloquent\Relations\Pivot;
use App\BaseModel;

class BelongsToMany extends ParentBelongsToMany
{
    /**
     * Attach a model to the parent.
     * 
     * Overrides default BelongsToMany behaviour to also call a function to 
     * perform other actions (if defined)
     *
     * @param  mixed  $id
     * @param  array  $attributes
     * @param  bool   $touch
     * @throws FatalErrorException
     * @return void
     */
    public function attach($id, array $attributes = [], $touch = true)
    {
        $related = $this->getRelated();
        if ($related instanceof BaseModel) {
            $attributes = $this->getRelated()->setAttributesBeforeAttach($this->getParent(), $this->table, $attributes, $id);
        }

        parent::attach($id, $attributes, $touch);
        
        if (!$related->afterAttach($id, class_basename($this->getParent()))) {
            throw new FatalErrorException('An internal error has occurred');
        }
    }

    /**
     * Override parent function to fire an updating event on the pivot class, as this function does not fire that event by default.
     *
     * Update an existing pivot record on the table.
     *
     * @param  mixed  $id
     * @param  array  $attributes
     * @param  bool   $touch
     * @return int
     */
    public function updateExistingPivot($id, array $attributes, $touch = true)
    {
        //get the pivot object before it is modified, and patch it with the attributes being updated
        $pivot = $this->where($this->getOtherKey(), $id)->get()->first()->pivot;
        foreach ($attributes as $attribute => $value) {
            $pivot->setAttribute($attribute, $value);
        }

        $ret = parent::updateExistingPivot($id, $attributes, $touch);
        if ($ret) {
            //if the update was successful, fire the event
            $pivot->getEventDispatcher()->until('eloquent.updating: ' . get_class($pivot), $pivot);
        }
        return $ret;
    }
}
