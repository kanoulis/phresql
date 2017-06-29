# phresql

## Start server
$ php -S localhost:8080

## Select
```
$ curl http://localhost:8080/api.php/notes -X GET
```

## Insert
```
$ curl http://localhost:8080/api.php/notes -X PUT -d '{"tag":"test", "entry":"a test"}'
```

## Update
```
$ curl http://localhost:8080/api.php/notes/1 -X PATCH -d '{"entry":"another test"}'
```

## Delete
```
$ curl http://localhost:8080/api.php/notes/1 -X DELETE
```
