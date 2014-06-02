BatchChildEditor
================

Processwire module for quick batch creation and editing of child page titles and names.

This module adds a new section at the bottom of the Children Tab when editing a page. You can choose to:

1. Add - adds newly entered page titles as child pages to the list of existing siblings. You could create a list of pages in Word or whatever and just paste them in here and viola!
2. Overwrite - Works similarly to Add, but overwrites all the existing children. There are checks that prevent this method working if there are any child pages with their own children or other content fields that are not empty. This check can be disabled in the module config settings, but please be very careful with this.
3. Edit - This allows you to rename existing child pages and add new child pages. It is non-destructive and so could be used on child pages that have their own children or other content fields (not just title). It is however not ideal for quick creation of new children or for changing child order.

###Access permission

This module requires a new permission: "batch-child-editor". This permission is created automatically on install and is added to the superuser role, but it is up to the developer to add the permission to other roles as required.


###Config Settings

Which pages and templates will have the editor available.

Which edit modes should be availble to the user. Note that superusers have both modes regardless of this setting.

Whether the name of the page should also be changed along with the title. This is a very important setting and should be considered carefully, especially is the child pages are URL accessible.

Whether users can decide whether the name is also changed or not.

Whether to disable content protection for existing child pages and their children.








