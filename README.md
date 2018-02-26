# Replicast
Replicate content across WordPress installs via the WP REST API.

## Roadmap

| Posts                               | Status |    Notes    |
|-------------------------------------|:------:|-------------|
| Creation                            |    X   |             |
| Edition                             |    X   |             |
| Delete (trash)                      |    X   | [1]         |
| Permanent Delete                    |    X   |             |
| Meta                                |    X   |             |
| Taxonomies (categories, tags, etc.) |    X   |             |
| Featured Image                      |    X   | [2][3]      |
| Deactivate local edition            |    X   |             |
| Gallery shortcode                   |        |             |

Notes:  
1. A filter was developed that transforms this delete into a permanent delete;
2. At the local edition screen, the remote thumbnail image is displayed with the link to the remote site;
3. Locally "remote" images aren't displayed;


| Page                     | Status |    Notes    |
|--------------------------|:------:|-------------|
| Criation                 |    X   |             |
| Edition                  |    X   |             |
| Delete (trash)           |    X   |             |
| Permanent Delete         |    X   |             |
| Meta                     |    X   |             |
| Deactivate local edition |    X   |             |


| Taxonomies               | Status |    Notes    |
|--------------------------|:------:|-------------|
| Criation                 |    X   |             |
| Edition                  |    X   |             |
| Deactivate local edition |    X   |             |
| Meta                     |    X   |             |


| Attachments                              | Status |    Notes    |
|------------------------------------------|:------:|-------------|
| Upload (individual edition page)         |    X   |             |
| Upload (JavaScript popup)                |        |             |
| Permanent Delete                         |        |             |
| Associate to the respective post         |    X   | [1]         |
| Deactivate local edition                 |    X   |             |

Notes:  
1. Featured images situation;  


| ACF                     | Status |    Notes    |
|-------------------------|:------:|-------------|
| Text                    |    X   |             |
| Related Posts           |    X   |             |
| Isolated Post Objects   |    X   |             |
| Date Picker             |    X   |             |
| Image                   |        |             |
| Gallery                 |        |             |
| Term "Meta"             |    X   |             |


### Others
* Create action or method `is_rest` and use this method instead of `! is_admin()`  
* <del>Improve Site management engine (unify Site URL and REST API URL fields)</del>  
* Add CSS class to body of edit page to make visual changes (hide fields) on remote sites
* Avoid that the meta REPLICAST_OBJECT_INFO field is returned by the remote site at requests by the central site
* Validate mandatory fields when a new "Site" is created
* Improve admin messages' management mechanism
* Improve log mechanism

### Notes
* Attachments meta fields only synchronize in a second request. 
  This happens because the /media endpoint only accepts the media file during the creation request, 
  ignoring additional data that may be present in the request.
* How to handle posts that were deleted in a remote site
    ```
    Client error: `DELETE http://yoursiteurl/wp-json/wp/v2/posts/3604` resulted in a `410 Gone` response: {"code":"rest_already_deleted","message":"The post has already been deleted.","data":{"status":410}} 
    410: Gone
    ```
    
## Contributions

Contribuitons are most welcome in their natural form of Pull Requests, the following guidelines are just to keep things following with ease:
* If it's something new, make sure it's not hidden somewhere in here already or that we didn't dismiss it for something else.
* Make sure you supply some arguments for the benefits/advantages your change provides.
