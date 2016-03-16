# Replicast

## TODO

### Posts
x Criação de posts
x Edição de posts
x Eliminação de posts
x Eliminação permanente de posts
x Sincronização de meta
x Sincronização de termos
x Sincronização do featured image
x Sincronização ACF (related fields)
x Desactivar edição local dos posts criados centralmente

### Páginas
x Criação de páginas
x Edição de páginas
x Eliminação de páginas
x Eliminação permanente de páginas
x Sincronização de meta
x Desactivar edição local das páginas criadas centralmente

### Taxonomias
x Criação de termos
x Edição de termos
- Desactivar edição local dos termos criados centralmente

### Attachments
x Upload de attachments (via página de edição individual)
- Upload de attachments (via popup JS)
- Eliminação permanente de attachments
x Associação de attachments ao post correspondente
- Desactivar edição local dos attachments criados centralmente

### Outros
- Melhorar mecanismo de gestão de sites (unificar campos Site URL e REST API URL)
- Melhorar mecanismo de gestão de mensagens de admin
- Melhorar mecanismo de logs
- Validar campos obrigatórios na criação de um "Site"

## Notas
- Os campos meta de um attachment só são sincronizados num segundo pedido. Isto porque o endpoint de media só aceita no corpo do pedido de criação um ficheiro, ignorando tudo o resto.
- Como lidar com posts que já foram eliminados num site remoto
    ```
    Client error: `DELETE http://cms.sonaesierra.dev/colombo/wp-json/wp/v2/posts/3604` resulted in a `410 Gone` response: {"code":"rest_already_deleted","message":"The post has already been deleted.","data":{"status":410}} 
    410: Gone
    ```
