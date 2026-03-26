UPDATE customer
SET phone_number = CONCAT('+60', SUBSTRING(phone_number, 5))
WHERE phone_number LIKE '+600%';
