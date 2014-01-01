RedBean Instance
==============

In a number of situations, a monolithic facade is not desirable. You need Instances.

Here, have an instance.

### Usage

```php
// Include or autoload the files in this library

$db = new RedBean_Instance();

// All static method calls are regular method calls now
$project = $db->dispense('project');

$project->name = 'Hello';

$db->store($project);
```
