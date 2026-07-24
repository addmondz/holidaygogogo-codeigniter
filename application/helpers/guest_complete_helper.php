<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Decide whether a single guest_list row is "complete" for the purpose of
 * advancing a booking's Guest List status (see Guest_List_Model::Are_All_Guests_Complete).
 *
 * Kept pure (no DB / no CI) so it can be unit tested and reused by the model.
 *
 * Rules:
 *  - Every guest needs Name, LastName, Gender and DateOfBirth.
 *  - Contact (Email + Mobile + CountryCodeID) is required for ADULT/INFANT but
 *    OPTIONAL for CHILD guests — children don't have their own phone/email.
 *  - Malaysian nationals need an IdentificationNumber.
 *
 * @param object|array $guest guest_list row carrying Name, LastName, Gender,
 *                            DateOfBirth, Email, Mobile, CountryCodeID, Type,
 *                            Nationality.
 * @return bool true when the row satisfies all required fields.
 */
if (!function_exists('guest_row_is_complete')) {
	function guest_row_is_complete($guest)
	{
		$g = (object) $guest;

		$name          = isset($g->Name) ? $g->Name : null;
		$last_name     = isset($g->LastName) ? $g->LastName : null;
		$gender        = isset($g->Gender) ? $g->Gender : null;
		$dob           = isset($g->DateOfBirth) ? $g->DateOfBirth : null;
		$email         = isset($g->Email) ? $g->Email : null;
		$mobile        = isset($g->Mobile) ? $g->Mobile : null;
		$country_code  = isset($g->CountryCodeID) ? $g->CountryCodeID : null;
		$type          = isset($g->Type) ? $g->Type : null;
		$nationality   = isset($g->Nationality) ? $g->Nationality : null;

		if (empty($name) || empty($last_name) || empty($gender) || empty($dob)) {
			return false;
		}

		// Contact is optional for child guests.
		if ($type != 'CHILD' && (empty($email) || empty($mobile) || empty($country_code))) {
			return false;
		}

		$ic = isset($g->IdentificationNumber) ? $g->IdentificationNumber : null;
		if (strtolower((string) $nationality) == 'malaysian' && empty($ic)) {
			return false;
		}

		return true;
	}
}
