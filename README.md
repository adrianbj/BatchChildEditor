BatchChildEditor
================

Processwire module for quick batch creation, editing, sorting, and deletion of all children under a given page.

This module adds a new section at the bottom of the Children Tab when editing a page. You can choose to:

1. Add - adds newly entered page titles as child pages to the list of existing siblings. You could create a list of pages in Word or whatever and just paste them in here and viola!
2. Overwrite - Works similarly to Add, but overwrites all the existing children. There are checks that prevent this method working if there are any child pages with their own children or other content fields that are not empty. This check can be disabled in the module config settings, but please be very careful with this.
3. Edit - This allows you to rename existing child pages, add new child pages, sort, and delete pages. It is non-destructive and so could be used on child pages that have their own children or other content fields (not just title). It also has a modal edit link from the page name.

###Access permission

This module requires a new permission: "batch-child-editor". This permission is created automatically on install and is added to the superuser role, but it is up to the developer to add the permission to other roles as required.


###Config Settings

Which pages and templates will have the editor available.

Which edit modes should be availble to the user.

Whether the name of the page should also be changed along with the title. This is a very important setting and should be considered carefully, especially is the child pages are URL accessible.

Whether users can decide whether the name is also changed or not.

Whether to disable content protection for existing child pages and their children.


## License

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

(See included LICENSE file for full license text.)






