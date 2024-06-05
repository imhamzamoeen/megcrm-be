<?php

declare(strict_types=1);

namespace App\Enums\Events;

use App\Models\Calendar;
use BenSampo\Enum\Enum;

final class SurveyBookedEnum extends Enum
{
    const TITLE = 'Survey Booked';

    const NOTIFICATION_TITLE = 'Survey Booking Notification';

    const CALENDAR_NAME = 'Surveys';

    const IS_FULL_DAY = true;

    public static function getCalendarId(): ?int
    {
        return Calendar::where('name', self::CALENDAR_NAME)->first()?->id;
    }

    public static function getDescriptionMessage(string $name, string $address, string $time)
    {
        return "You have a survey booked at the location : {$address} on {$time} with {$name}.";
    }

    public static function getNotificationSubtitle(string $name, string $time)
    {
        return "You have a survey booked with {$name} at { $time }.";
    }

    public static function getTwilioMessage($lead, string $time, string $from): string
    {
        $message = "Hi {$lead['first_name']},\n\nDomestic Energy Survey has been booked for:\n\n{$lead['plain_address']} at\n\n{$time}.\n\nIf you have any query, ";

        if (isset($lead['lead_generator']['email'])) {
            $message .= "please contact us via email at: {$lead['lead_generator']['email']} or ";
        }


        $message .= "\n\nRegards,\n{$from}";

        return $message;
    }
}
