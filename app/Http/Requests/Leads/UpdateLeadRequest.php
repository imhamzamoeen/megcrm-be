<?php

namespace App\Http\Requests\Leads;

use App\Actions\Common\BaseFormRequest;

class UpdateLeadRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string'],
            'first_name' => ['sometimes', 'required', 'string'],
            'middle_name' => ['nullable', 'string'],
            'last_name' => ['sometimes', 'required', 'string'],
            'email' => ['sometimes', 'nullable'],
            'phone_no' => ['sometimes', 'required', 'string'],
            'dob' => ['sometimes', 'required', 'string'],
            'address' => ['sometimes', 'required', 'string'],
            'post_code' => ['sometimes', 'required', 'string'],
            'measures' => ['sometimes', 'array'],
            'epc' => ['nullable', 'string'],
            'gas_safe' => ['nullable', 'string'],
            'recommend' => ['nullable', 'string'],
            'has_second_receipent' => ['sometimes', 'required', 'boolean'],
            'second_receipent' => ['sometimes', 'required', 'array'],
            'second_receipent.first_name' => [
                'sometimes',
                'nullable',
                'required_if:has_second_receipent,true',
                'string',
            ],
            'second_receipent.last_name' => [
                'sometimes',
                'nullable',
                'required_if:has_second_receipent,true',
                'string',
            ],
            'second_receipent.middle_name' => [
                'sometimes',
                'nullable',
                'nullable',
                'string',
            ],
            'second_receipent.dob' => [
                'sometimes',
                'nullable',
                'required_if:has_second_receipent,true',
                'date',
            ],
            'is_marked_as_job' => ['sometimes', 'required', 'boolean'],
            'job_type_id' => ['sometimes', 'nullable', 'exists:job_types,id'],
            'fuel_type_id' => ['sometimes', 'nullable', 'exists:fuel_types,id'],
            'surveyor_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'lead_generator_id' => ['sometimes', 'nullable', 'exists:lead_generators,id'],
            'lead_source_id' => ['sometimes', 'nullable', 'exists:lead_sources,id'],
            'benefits' => ['sometimes', 'array'],
            'notes' => ['sometimes', 'nullable'],
            'lead_customer_additional_detail' => ['sometimes', 'array'],
            'lead_customer_additional_detail.contact_method' => ['nullable', 'string'],
            'lead_customer_additional_detail.priority_type' => ['nullable', 'string'],
            'lead_customer_additional_detail.time_to_contact' => ['nullable', 'string'],
            'lead_customer_additional_detail.time_at_address' => ['nullable', 'date'],
            'lead_customer_additional_detail.is_customer_owner' => ['sometimes', 'boolean', 'required'],
            'lead_customer_additional_detail.is_lead_shared' => ['sometimes', 'boolean', 'required'],
            'lead_customer_additional_detail.is_datamatch_required' => ['sometimes', 'boolean', 'required'],
            'lead_customer_additional_detail.datamatch_progress' => ['nullable', 'string'],
            'lead_customer_additional_detail.datamatch_progress_date' => ['nullable', 'date'],
            'lead_customer_additional_detail.lead_id' => ['sometimes', 'exists:leads,id'],
            'survey_booking' => ['sometimes', 'array'],
            'submission' => ['sometimes', 'array'],
            'lead_additional' => ['sometimes', 'array'],
            'lead_additional.datamatch_confirmed' => ['nullable', 'boolean'],
            'lead_additional.land_registry_confirmed' => ['nullable', 'boolean'],
            'lead_additional.proof_of_address_confirmed' => ['nullable', 'boolean'],
            'lead_additional.epr_report_confirmed' => ['nullable', 'boolean'],
            'lead_additional.is_pre_checking_confirmed' => ['nullable', 'boolean'],
            'lead_additional.gas_connection_before_april_2022' => ['nullable', 'boolean'],
            'installation_bookings' => ['nullable', 'array'],
        ];
    }

    public function attributes()
    {
        return [
            'has_second_receipent' => 'second receipent',
            'second_receipent.first_name' => 'second receipent first name',
            'second_receipent.middle_name' => 'second receipent middle name',
            'second_receipent.last_name' => 'second receipent last name',
            'second_receipent.dob' => 'second receipent date of birth',
        ];
    }


    public function validated($key = null, $default = null): array
    {
        $oldData = parent::validated();

        if (data_get($oldData, 'lead_customer_additional_detail.is_datamatch_required', null) === true ) {
            data_set($oldData, 'lead_customer_additional_detail.datamatch_progress', 'Datamatch Required');
        }
        else  {
            data_set($oldData, 'lead_customer_additional_detail.datamatch_progress', 'Not Sent');
        }
        return $oldData;
    }
}
