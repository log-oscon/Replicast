# Replicast

## TODO
- Delete permanently a post on remote sites
- Deal with posts that has been already trashed in a remote site
    Client error: `DELETE http://cms.sonaesierra.dev/colombo/wp-json/wp/v2/posts/3604` resulted in a `410 Gone` response: {"code":"rest_already_deleted","message":"The post has already been deleted.","data":{"status":410}} 
    410: Gone
- Proper request and response logging
- Make site taxonomy API fields required for submission
