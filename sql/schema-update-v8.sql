-- New users default profile photo = OBO logo
ALTER TABLE `users`
  ALTER COLUMN `profile_photo` SET DEFAULT 'assets/images/OBO LOGO.png';
