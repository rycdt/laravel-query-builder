# REST API Request Format / Parameters

## Query Builder
Provides comprehensive search capabilities for API endpoints with sorting, filtering, aggregates and includes.
```
{
 first: 10,
 page: 1,
 selects: [],
 concats: [],
 where: {},
 sort: [],
 has: {},
 aggregates: [],
 includes: []
}
```

## Validation Builder
### Custom Validation Message
```
{
 validationMessages: {
   column_name: {
     rule: 'unique',
     message: 'My custom unique message.'
   }
 }
}
```
### Custom Validation Rule
```
{
 validationRules: {
   column_name: {
     replace: 'unique',
     with: 'iunique'
   }
 }
}
```

## Requirement
* **PHP** >= 7.2
* See [Packagist (rycdt/laravel-query-builder)](https://packagist.org/packages/rycdt/laravel-query-builder)
