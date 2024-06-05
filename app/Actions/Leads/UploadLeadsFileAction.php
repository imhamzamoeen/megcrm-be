<?php

namespace App\Actions\Leads;

use App\Classes\LeadResponseClass;
use App\Enums\AppEnum;
use App\Exports\CreateFailedLeadsExport;
use App\Http\Requests\DataMatch\UploadDataMatchRequest;
use App\Imports\Leads\LeadDataMatchImport;
use App\Imports\Leads\LeadsImport;
use App\Models\DataMatchFile;
use App\Notifications\SendDataMatchResultsNotification;
use App\Traits\Jsonify;
use Exception;
use GuzzleHttp\Promise\Create;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;

class UploadLeadsFileAction
{
    use Jsonify;

    public function __construct(public LeadResponseClass $leadResponseClass)
    {
    }

    public function execute(Request $request): JsonResponse
    {
        try {
            $exampleHeader = [
                'website',
                'name',
                'email',
                'contact_number',
                'dob',
                'postcode',
                'address',
                'what_is_your_home_ownership_status',
                'benefits',
            ];
            $this->CheckFileHeaderErrors($exampleHeader, $request->file('file'));
            Excel::import(new LeadsImport($this->leadResponseClass), $request->file('file'));

            return $this->success('File was uploaded successfully.', data: $this->leadResponseClass);
        } catch (Exception $e) {
            Log::channel('lead_file_read_log')->info(
                'Error importing exception ' . $e->getMessage()
            );

            return $this->error($e->getMessage());
        }
    }

    public function executeLeadsDataMatchResultUpload(UploadDataMatchRequest $request)
    {
        try {
            $foundLeadEnteries = collect();
            $exampleHeader = [
                'landlord_surname',
                'landlord_forename',
                'surname',
                'forename',
                'date_of_birth',
                'property_name_or_number',
                'address_line_1',
                'address_line_2',
                'address_line_3',
                'town',
                'county',
                'postcode',
                'urn',
                'eco_3_verification_status',
                'eco_4_verification_status',
                'owner_status',
                'date_uploaded',
                'date_processed_by_dwp',
            ];

            $this->CheckFileHeaderErrors($exampleHeader, $request->file('file'), 3);
            Excel::import(new LeadDataMatchImport($this->leadResponseClass, $foundLeadEnteries), $request->file('file'));

            $Model = DataMatchFile::make();
            $Model->id = (string) Str::uuid();
            $fileName = "DataMatchUploaded_{$Model->id}.csv";
            $Model->file_name = $fileName;
            $Model->file_path = "DataMatchUploaded/" . now()->format('Ymd') . "/{$Model->id}/{$fileName}";
            $Model->created_by_id = auth()->user()->id;
            $Model->type = AppEnum::FILE_TYPE_DATA_MATCH_UPLOAD;
            $Model->save();
            // Store on default disk
            $path = $request->file('file')->storeAs(
                "DataMatchUploaded/" . now()->format('Ymd') . "/" . $Model->id,
                $fileName,
                'local'
            );
            $reponse = null;
            if (filled($this->leadResponseClass->failedLeads)) {
                // some leads got failed  just make a new file for it
                $reponse = $this->makeFileFromArray($this->leadResponseClass->failedLeads);
            }

            if (filled($foundLeadEnteries)) {
                /* send the results to the  lead generator email */
                foreach ($foundLeadEnteries as $index => $value) {
                    # code...
                    $explodedResults = explode(",|,", $index);
                    $LeadGenEmail = data_get($explodedResults, '0', null);
                    $LeadGenPhone = $this->getFormattedNumber(data_get($explodedResults, '1', null));
                    Notification::route('mail', $LeadGenEmail)
                        ->route('twilio', $LeadGenPhone)
                        ->notify(new SendDataMatchResultsNotification($value));
                }

            }

            return $this->success($reponse, data: $this->leadResponseClass);
        } catch (Exception $e) {
            Log::channel('data_match_result_file_read_log')->info(
                'Error importing exception ' . $e->getMessage()
            );

            return $this->error($e->getMessage());
        }
    }

    protected function getFormattedNumber(?string $phoneNo)
    {

        if (!$phoneNo || str()->length($phoneNo) < 10) {
            return null;
        }
        return '+44' . substr($phoneNo, -10);
    }
    private function CheckFileHeaderErrors(array $headersArray, $file, $headingRow = 1)
    {
        $headings = (new HeadingRowImport($headingRow))->toArray($file)[0][0];

        if (count($headings) < count($headersArray)) {

            throw new Exception('File has invalid header. (less headings)' . json_encode($headings));
        }

        $headings = array_map('strtolower', $headings);

        $headerDifference = array_diff($headersArray, $headings);

        throw_if(filled($headerDifference), new Exception('File has invalid header ( not matched ).' . json_encode($headerDifference)));
    }


    public function makeFileFromArray(array $fileEnteries): ?string
    {
        try {
            $Model = DataMatchFile::make();
            $Model->id = (string) Str::uuid();
            $fileName = 'DataMatchFailedLeads' . now()->format('YmdHis') . '.csv';
            $result = Excel::store(new CreateFailedLeadsExport($fileEnteries), "DataMatchFailedLeads/" . now()->format('Ymd') . "/{$Model->id}/{$fileName}", 'local');
            if ($result) {

                $Model->file_name = $fileName;
                $Model->file_path = "DataMatchFailedLeads/" . now()->format('Ymd') . "/{$Model->id}/{$fileName}";
                $Model->created_by_id = auth()->user()->id;
                $Model->type = AppEnum::FILE_TYPE_DATA_MATCH_FAILED_LEADS;
                $Model->save();
                return URL::temporarySignedRoute(
                    'data_match.file_download',
                    now()->addMinutes(10),
                    [
                        'uuid' => $Model->id,
                        'url' => $Model->file_name,

                    ]
                );
            }
            return null;
        } catch (Exception $e) {
            Log::channel('LOG_SLACK_EXCEPTIONS_CHANNEL_URL')->info("Error making file from failed leads by user " . auth()->user()->email . " from the function " . __FUNCTION__ . " with exception " . $e->getMessage());
            return null;
        }
    }
}
