<?php

namespace App\Services;

use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Illuminate\Support\Facades\Log; // <<< TAMBAHKAN BARIS INI

class GoogleCalendarService
{
    protected Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Creates a new calendar event.
     *
     * @param string $summary The title of the event.
     * @param string|null $description The description of the event.
     * @param string $startTime The start time (e.g., "2025-06-20T10:00:00").
     * @param string $endTime The end time (e.g., "2025-06-20T11:00:00").
     * @param array $attendees Email addresses of attendees.
     * @param bool $addMeetLink Whether to add a Google Meet link.
     * @return Event|null The created event object, or null on failure.
     */
    public function createEvent(string $summary, ?string $description, string $startTime, string $endTime, array $attendees = [], bool $addMeetLink = true): ?Event
    {
        $calendarService = new Calendar($this->client);
        $event = new Event([
            'summary' => $summary,
            'description' => $description,
            'sendNotifications' => true, // Kirim notifikasi ke peserta
        ]);

        $start = new EventDateTime();
        $start->setDateTime($startTime);
        $start->setTimeZone('Asia/Jakarta'); // Sesuaikan dengan timezone Anda
        $event->setStart($start);

        $end = new EventDateTime();
        $end->setDateTime($endTime);
        $end->setTimeZone('Asia/Jakarta'); // Sesuaikan dengan timezone Anda
        $event->setEnd($end);

        // Tambahkan peserta
        $eventAttendees = [];
        foreach ($attendees as $email) {
            $eventAttendees[] = ['email' => $email];
        }
        if (!empty($eventAttendees)) {
            $event->setAttendees($eventAttendees);
        }

        // Tambahkan Google Meet (Hangouts Meet)
        if ($addMeetLink) {
            $conferenceData = new Calendar\ConferenceData();
            $createRequest = new Calendar\CreateConferenceRequest();
            $createRequest->setRequestId(uniqid()); // ID unik untuk permintaan Meet
            $conferenceData->setCreateRequest($createRequest);
            $event->setConferenceData($conferenceData);
        }

        $calendarId = 'primary'; // Menggunakan kalender utama pengguna

        try {
            $createdEvent = $calendarService->events->insert($calendarId, $event, ['conferenceDataVersion' => 1]);
            return $createdEvent;
        } catch (\Google\Service\Exception $e) {
            Log::error("Error creating Google Calendar event: " . $e->getMessage()); // Menggunakan Log::error
            return null;
        }
    }
}