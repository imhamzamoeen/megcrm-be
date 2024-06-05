<?php

namespace App\Models;

use App\Actions\Common\BaseModel;
use App\Traits\Common\HasRecordCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LeadGenerator extends BaseModel
{
    use HasFactory, HasRecordCreator;

    protected $fillable = [
        'name',
        'sender_id',
        'email',
        'phone_no',
        'aircall_number',
        'mask_name',
    ];

    protected array $allowedIncludes = [
        'createdBy',
        'leadGeneratorManagers'
    ];

    public function leadGeneratorManagers()
    {
        return $this->belongsToMany(User::class, LeadGeneratorManager::class)
            ->withPivot('created_by_id')
            ->withTimestamps();
    }

    public function leads()
    {
        return $this->hasMany(Lead::class);
    }

    public function leadGeneratorAssignments()
    {
        return $this->belongsToMany(User::class, LeadGeneratorAssignment::class)
            ->withPivot('created_by_id')
            ->withTimestamps();
    }
}
