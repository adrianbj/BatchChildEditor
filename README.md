BatchChildEditor
================

Processwire modules for quick batch creation and editing of child page titles and names

This module adds a new section at the bottom of the Children Tab when editing a page. You can choose to:

1) Create/Overwrite - This allows super quick creation and overwriting of child pages. You could create a list of pages in Word or whatever and just paste them in here and viola! There are checks that prevents this method working if there are any child pages with their own children or other content fields that are not empty. These checks are important because of the destructive way it deletes and recreates all the child pages.
2) Edit - This allows you to rename existing child pages. It is non-destructive and so could be used on child pages that have their own children or other content fields (not just title). It is however not ideal for quick creation of new children or for changing child order.

###Config Settings

Which user roles will have access to the batch editor. SuperUser has access by default, but you can specify which other roles will have access.
Which pages and templates will have the editor available.
Whether the name of the page should also be changed along with the title.
Whether users can decide whether the name is also changed or not.








