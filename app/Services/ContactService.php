<?php

namespace App\Services;

use App\Events\NewRegisteredContact;
use App\Models\Business;
use App\Models\Contact;
use App\Models\User;
use Carbon\Carbon;

/*******************************************************************************
 * Contact Service Layer
 ******************************************************************************/
class ContactService
{
    /**
     * Register a new Contact.
     *
     * @param User     $user
     * @param Business $business
     * @param array    $data
     *
     * @return App\Models\Contact
     */
    public function register(User $user, Business $business, $data)
    {
        if (false === $contact = self::getExisting($user, $business, $data['nin'])) {
            $contact = Contact::create($data);
            $business->contacts()->attach($contact);

            logger()->info("Contact created contactId:{$contact->id}");

            self::updateNotes($business, $contact, $data['notes']);
        }

        event(new NewRegisteredContact($contact));

        return $contact;
    }

    /**
     * Find an existing contact with the same NIN.
     *
     * @param User     $user
     * @param Business $business
     * @param string   $nin
     *
     * @return App\Models\Contact
     */
    public function getExisting(User $user, Business $business, $nin)
    {
        if (trim($nin) == '') {
            return false;
        }

        $existingContacts = Contact::whereNotNull('nin')->where(['nin' => $nin])->get();

        foreach ($existingContacts as $existingContact) {
            logger()->info("[ADVICE] Found existing contactId:{$existingContact->id}");

            if ($existingContact->isSubscribedTo($business->id)) {
                logger()->info('[ADVICE] Existing contact is already linked to business');

                return $existingContact;
            }
        }

        return false;
    }

    /**
     * Find a contact within a Business addressbok.
     *
     * @param Business $business
     * @param Contact  $contact
     *
     * @return App\Models\Contact
     */
    public function find(Business $business, Contact $contact)
    {
        return $business->contacts()->find($contact->id);
    }

    /**
     * Update Contact attributes.
     *
     * @param Business $business
     * @param Contact  $contact
     * @param array    $data
     * @param string   $notes
     *
     * @return void
     */
    public function update(Business $business, Contact $contact, $data = [], $notes = null)
    {
        $contact->firstname = $data['firstname'];
        $contact->lastname = $data['lastname'];
        $contact->email = $data['email'];
        $contact->nin = $data['nin'];
        $contact->gender = $data['gender'];
        $contact->birthdate = $data['birthdate'];
        $contact->mobile = $data['mobile'];
        $contact->mobile_country = $data['mobile_country'];

        $contact->save();

        self::updateNotes($business, $contact, $notes);
    }

    /**
     * Detach a Contact froma Business addressbok.
     *
     * @param Business $business
     * @param Contact  $contact
     *
     * @return int
     */
    public function detach(Business $business, Contact $contact)
    {
        return $contact->businesses()->detach($business->id);
    }

    /////////////
    // HELPERS //
    /////////////

    /**
     * Update notes from pivot table.
     *
     * @param Business $business
     * @param Contact  $contact
     * @param string   $notes
     *
     * @return void
     */
    protected function updateNotes(Business $business, Contact $contact, $notes)
    {
        if ($notes) {
            $business->contacts()->find($contact->id)->pivot->update(['notes' => $notes]);
        }
    }
}
