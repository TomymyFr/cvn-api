# cvn-api

## Request

* `users` (optional): Pipe separated list of wiki user names.
* `pages` (optional): Pipe separated list of wiki page names.
* `callback` (optional): Simple javascript identifier (character class: `a-z A-Z 0-9 _ . ][ '"`).
  <br/>If you're doing cross-domain requests from javascript, you can use this parameter to request a
  JSON-P style response instead of plain JSON.

## Response

### Headers

The server will respond with content-type `application/json` and a body containing a valid JSON
string.

If you specify a callback, the server will instead respond with `text/javascript` and invoke said
function with an object literal.

In case of an error, an HTTP 400 status is returned and the response body will only contain an
`error` with one of the [error codes](#possible-errors).

### Body

The following properties are returned in a successful request. Note that response only includes users and pages found in the database, there is no empty placeholder for names specified in the request that weren't listed anywhere.

* `users`: An object keyed by user name containing:
 * `type`: The type of list this user is on.<br/>Value: One of "blacklist", "whitelist" or "greylist
 * `comment`: Comment left by the adder.<br/>Value: A string or false if no reason was given.
 * `expiry`: The timestamp of when this list item expires.<br/>Value: The number of seconds since the Unix Epoch (January 1 1970 00:00:00 GMT) or `false` for indefinitely.
 * `adder`: The user who added this list item.
* `pages`: An object keyed by page name.
 * `comment`: Comment left by the adder.
 * `expiry`: The timestamp of when this list item expires.
 * `adder`: The user who added this list item.
* `lastUpdate`: The timestamp of when the database was last modified.

### Possible errors:
* `missing-query`: At least one of `users` or `pages` must be specified and non-empty.
* `invalid-callback`: The callback specified is empty or contains one or more illegal characters.

## Usage

### Usage in javascript

Example using [`jQuery.ajax`](http://api.jquery.com/jQuery.ajax/):

```js
	// Check the status of the following users and pages
	var users = ['127.0.0.1', 'Krinkle'];
	var pages = ['Main Page', 'Template:Delete'];

	jQuery.ajax({
		url: 'http://cvn.example.org/api.php',
		data: {
			users: users.join('|'),
			pages: pages.join('|')
		},
		dataType: 'jsonp'
	}).done(function (data) {
			console.log(data);
			/*
			{
				"users": {
					"MyName": {
						"type": "..",
						"comment": "..",
						"expiry": "..",
						"adder": ".."
					}
				},
				"pages": {
					"Main Page": {
						"comment": "..",
						"expiry": "..",
						"adder": ".."
					}
				}
			}
			*/
	});
```

### Usage in PHP

Example using [`file_get_contents`](http://php.net/file_get_contents):

```php
<?php
	// Check the status of the following users and pages
	$users = array('127.0.0.1', 'Krinkle');
	$pages = array('Main Page', 'Template:Delete');

	$result = file_get_contents(
		'http://cvn.example.org/api.php?' . http_build_query( array(
			'users' => implode( '|', $users ),
			'pages' => implode( '|', $pages ),
	));
	$data = json_decode($result, true);

	echo var_export($data);
	/*
	array(
		'users' => array(
			'MyName' => array(
				'type' => '..',
				'comment' => '..',
				'expiry' => '..',
				'adder' => '..',
			),
		),
		'pages' => array(
			'Main Page' => array(
				'comment' => '..',
				'expiry' => '..',
				'adder' => '..',
			),
		),
	)
	*/
?>
```

## Install

* Clone this repository.
* Copy the database of a [CVNBot](https://github.com/countervandalism/CVNBot)
  to the `data` directory.
* Expose `public_html` directory in the document root of a web server
  that supports PHP 5.3.10 or higher (e.g. Apache or NGINX).
* Done!


## Copyright and license

See [LICENSE.txt](./MIT-LICENSE.txt).
