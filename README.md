# Replicast

## TODO
* Refazer mecanismo das "admin notices" (evitar duplicação)  


## Roadmap

| Posts                               | Estado | Observações |
|-------------------------------------|:------:|-------------|
| Criação                             |    X   |             |
| Edição                              |    X   |             |
| Eliminação (trash)                  |    X   | [1]         |
| Eliminação permanente               |    X   |             |
| Meta                                |    X   |             |
| Taxonomias (categorias, tags, etc.) |    X   |             |
| Featured Image                      |    X   | [2][3]      |
| Desactivar edição local             |    X   |             |
| Gallery shortcode                   |        |             |

Observações:  
1. Foi desenvolvido um filtro que torna esta eliminação em eliminação permanente;  
2. No ecrã de edição do post local é mostrado o thumbnail da imagem remota com link para edição no site remoto;  
3. Localmente as imagens "remotas" não são mostradas;  


| Páginas                 | Estado | Observações |
|-------------------------|:------:|-------------|
| Criação                 |    X   |             |
| Edição                  |    X   |             |
| Eliminação (trash)      |    X   |             |
| Eliminação permanente   |    X   |             |
| Meta                    |    X   |             |
| Desactivar edição local |    X   |             |


| Taxonomias              | Estado | Observações |
|-------------------------|:------:|-------------|
| Criação                 |    X   |             |
| Edição                  |    X   |             |
| Desactivar edição local |    X   |             |
| Meta                    |    X   |             |


| Attachments                              | Estado | Observações |
|------------------------------------------|:------:|-------------|
| Upload (via página de edição individual) |    X   |             |
| Upload (via popup JS)                    |        |             |
| Eliminação permanente                    |        |             |
| Associação ao post correspondente        |    X   | [1]         |
| Desactivar edição local                  |    X   |             |

Observações:  
1. O caso das featured images;  


| ACF                     | Estado | Observações |
|-------------------------|:------:|-------------|
| Texto                   |    X   |             |
| Related Posts           |    X   |             |
| Isolated Post Objects   |    X   |             |
| Date Picker             |    X   |             |
| Image                   |        |             |
| Gallery                 |        |             |
| Term "Meta"             |    X   |             |


### Outros
* Criar action ou método `is_rest` e usar esse método em vez do `! is_admin()`  
* <del>Melhorar mecanismo de gestão de sites (unificar campos Site URL e REST API URL)</del>  
* Adicionar classe CSS ao body da página de edição para fazer alterações visuais (esconder campos) nos sites remotos  
* Evitar que o campo de meta REPLICAST_OBJECT_INFO seja retornado pelo site remoto na resposta ao pedido do central  
* Validar campos obrigatórios na criação de um "Site"  
  - Ver como é que o ACF faz para validar os campos que adiciona aos termos no Sierra Calendar - Authors
* Melhorar mecanismo de gestão de mensagens de admin  
* Melhorar mecanismo de logs  

### Notas
* Os campos meta de um attachment só são sincronizados num segundo pedido. 
  Isto porque o endpoint de /media só aceita no pedido de criação o ficheiro de media, 
  ignorando dados adicionais que vão no mesmo pedido.
* Como lidar com posts que já foram eliminados num site remoto
    ```
    Client error: `DELETE http://cms.sonaesierra.dev/colombo/wp-json/wp/v2/posts/3604` resulted in a `410 Gone` response: {"code":"rest_already_deleted","message":"The post has already been deleted.","data":{"status":410}} 
    410: Gone
    ```


### Sonae Sierra

| Stores                           | Estado | Observações |
|----------------------------------|:------:|-------------|
| Criar status 'Imported'          |        |             |
| Não propagar 'Imported'          |        |             |
| Status 'Draft' remove dos locais |        |             |
| Título                           |    X   |             |
| Texto                            |    X   |             |
| Categorias                       |    X   |             |
| Tags                             |    X   |             |
| Logo                             |        |             |
| Gallery                          |        |             |
| Contacts                         |    X   |             |
| Shopping Hours                   |    X   |             |
| Store Management                 |    X   | [1]         |
| Editing Permissions              |    -   | [2]         |

Observações:  
1. O campo "Relationship Group" só é sincronizado se o termo seleccionado já existir no site remoto;  
2. Não está contemplada a sincronização de utilizadores. Para além disso, não existe o conceito de "Editing Permissions" no CIDB;


| What's On - Event | Estado | Observações |
|-------------------|:------:|-------------|
| Título            |    X   |             |
| Texto             |    X   |             |
| Categorias        |    X   |             |
| Tags              |    X   |             |
| Hero Image        |        |             |
| Related           |    X   |             |
| Event Details     |    X   |             |
| Featured Image    |        |             |


| What's On - Article | Estado | Observações |
|---------------------|:------:|-------------|
| Título              |    X   |             |
| Texto               |    X   |             |
| Categorias          |    X   |             |
| Tags                |    X   |             |
| Hero Image          |        |             |
| Related             |    X   |             |
| Featured Image      |        |             |


