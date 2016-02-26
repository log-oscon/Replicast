# Replicast

## TODO

### Posts
x Criação de posts
x Edição de posts
x Eliminação de posts
- Eliminação permanente de posts
x Sincronização de meta
- Sincronização de termos
- Sincronização do featured image
- Sincronização de media via editor WYSIWYG
- Sincronização ACF (related fields)

### Páginas
x Criação de páginas
x Edição de páginas
x Eliminação de páginas
- Eliminação permanente de páginas
x Sincronização de meta

### Media
x Upload de media (via página de edição individual)
- Upload de media (via popup JS)
- Eliminação permanente de media
x Associação de media ao post correspondente

### Outros
- Melhorar mecanismo de gestão de mensagens de admin
- Melhorar mecanismo de logs
- Validar campos obrigatórios na criação de um "Site"

## Notas
- Como lidar com posts que já foram eliminados num site remoto
    ```
    Client error: `DELETE http://cms.sonaesierra.dev/colombo/wp-json/wp/v2/posts/3604` resulted in a `410 Gone` response: {"code":"rest_already_deleted","message":"The post has already been deleted.","data":{"status":410}} 
    410: Gone
    ```
