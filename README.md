# Replicast

## Roadmap

| Posts                               | Estado | Observações |
|-------------------------------------|:------:|-------------|
| Criação                             |    X   |             |
| Edição                              |    X   |             |
| Eliminação (trash)                  |    X   | [1]         |
| Eliminação permanente               |    X   |             |
| Meta                                |    X   |             |
| Taxonomias (categorias, tags, etc.) |    X   |             |
| Featured Image                      |    X   | [2][3][4]   |
| Desactivar edição local             |    X   |             |

Observações:  
1. Foi desenvolvido um filtro que torna esta eliminação em eliminação permanente;  
2. Localmente é criada uma imagem de 1x1 px que referencia a imagem remota;  
3. No ecrã de edição do post local é mostrado o thumbnail da imagem remota com link para edição no site remoto;  
4. Localmente as imagens "remotas" (1x1 px) não são mostradas;  


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
| Desactivar edição local |        |             |


| Attachments                              | Estado | Observações |
|------------------------------------------|:------:|-------------|
| Upload (via página de edição individual) |    X   |             |
| Upload (via popup JS)                    |        |             |
| Eliminação permanente                    |        |             |
| Associação ao post correspondente        |    X   | [1]         |
| Desactivar edição local                  |    X   |             |

Observações:  
1. O caso das featured images;  


| ACF            | Estado | Observações |
|----------------|:------:|-------------|
| Texto          |    X   |             |
| Related Posts  |    X   |             |
| Date Picker    |    X   |             |
| Related Images |        |             |


### Outros
* <del>Melhorar mecanismo de gestão de sites (unificar campos Site URL e REST API URL)</del>
* Validar campos obrigatórios na criação de um "Site"
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
