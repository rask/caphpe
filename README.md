# Caphpe

Caphpe (pronounced like *caffe* or something) is a simple volatile in-memory
key-value storage written in PHP.

Originally the idea was to create a Memcached clone using just PHP as an experiment.

## Installation

### Requirements

-   PHP CLI with sockets enabled

Make sure you have a relatively new PHP CLI installation available. Caphpe has been
developed with PHP5.5+ in mind but is currently only tested on PHP 7. Please try it
out and report if something does not work.

### Using the built PHAR

1.  Download `caphpe.phar`
2.  Set as executable with `chmod +x caphpe.phar`
3.  Run it.

### Using the source

1.  Clone this repository
2.  Make sure `bin/caphpe` is executable
3.  Install dependencies using `composer install`
4.  Run `bin/caphpe`.

## Usage

### Starting a Caphpe instance

Start a Cahphe instance with the default configuration:

    $ caphpe

Start a Caphpe instance with custom configuration:

    $ caphpe [options]

Where `[options]` may contain any of the following:

    --host=<host>|-h <host>
        IP or hostname to listen on
    
    --port=<port>|-p <port>
        Port to listen on
        
    --verbosity=<verbosity>|-v <verbosity>
        How verbose the instance output to STDOUT and STDERR is, use either 1, 2 or 3
        
    --memorylimit=<limit>|-m <limit>
        Limit the amount of memory (in megabytes) Caphpe uses for itself

Defaults are as follows:
    
    --host=127.0.0.1
    --port=10808
    --verbosity=1
    --memorylimit=64
    
Caphpe will start and stay in the foreground unless otherwise sent to background. It
will output STDOUT and STDERR messages depending on the verbosity parameter.

### Using the cache

Caphpe is a volatile in-memory cache. If the Caphpe instance is stopped or killed,
all data saved into its memory is lost. Caphpe currently has no grouping system, and
all cached values are stored into the same namespace. You can use your own key format
to define pseudo-grouping for values.

#### Connecting and interacting

Currently Caphpe provides an IP address based TCP connection interface which reads
newline separated commands. You can send and receive data using a stream socket
connection.

Caphpe reads commands that end in newlines.

Format your requests to the interface as such:

    <command> <parameters>\n
    
In commands the `<key>` parameter is always the first one, and the numerical
`<timeout>` parameter is always the last one (though optional). See what commands are
available later in this README.

You can try Caphpe with telnet (tested with Windows 7 telnet):

    telnet> o 127.0.0.1 10808
    > add mykey value
    1
    > has mykey
    1
    > get mykey
    value
    > has nokey
    
    > flush
    1
    > has mykey
    
    > close
    telnet>

#### Keys, values, types and timeouts

##### Keys

**Keys** are restricted to 64 characters in length and can only contain the
following:

-   `a-z`
-   `A-Z`
-   `0-9`
-   `.` and `_`

##### Values

**Values** are stored as strings by default. You can pass in a flag for each value to
save internally as either a string, an integer or a boolean.

**Warning**: currently Caphpe can't operate on strings that contain newline
characters (`\n`) as it uses those to delimit commands internally. For now libraries
should switch newlines to some other string sequences when writing and reading from
Caphpe.

Empty or no data is represented as an empty string.

##### Types

**Types** determine how Caphpe internally stores a value. There are three available
types:

-   String (denoted with `s`)
-   Integer (denoted with `i`)
-   Boolean (denoted with `b`)

All values are saved as string by default (unless PHP does some magic casting
somewhere). When using commands you need to use the flags `s`, `i`, and `b` to save
values as certain types. Usage instructions available below in the *commands*
section.

##### Timeouts

**Timeouts** determine when a value should be considered to be stale. Supply timeouts
as the *amount of seconds from now* when a value should be considered stale. Caphpe
clears the stale values cache at regular intervals while running, but each command
checks the staleness before returning values.

Setting the timeout value to `0` will make the cached value last "forever" and this
is the default behaviour if no value is supplied.

Timeouts are considered the last numeric characters in a command, preceded by a
space. This means some string values ending in numbers may be misinterpreted as
containing a timeout parameter.

#### Commands

Currently Caphpe supports the following commands:

##### add

    add <key> <type>?|<value> <timeout>?

Add a new cache value. If the key already exists nothing is done. Returns `1` if
successful, `<empty string>` if not. Examples:

    add mykey somevalue
    add mykey s|this is a string value
    add mykey b|1 3600
    add mykey i|123456
    add mykey value with a timeout 3600

##### set

    set <key> <type>?|<value> <timeout>?

Set a cache value. Will override values if the key exists. Returns `1` if successful,
`<empty string>` if not. Examples:
                        
    set mykey somevalue
    set mykey s|this is a string value
    set mykey b|1 3600
    set mykey i|123456
    set mykey value with a timeout 3600

##### replace

    replace <key> <type>?|<value> <timeout>?

Replace a cache value. If the key does not exist the nothing is done. Returns `1` if
successful, `<empty string>` if not. Examples:
                                    
    replace mykey somevalue
    replace mykey s|this is a string value
    replace mykey b|1 3600
    replace mykey i|123456
    replace mykey value with a timeout 3600

##### increment

    increment <key> <timeout>?

Increment a numeric cached value. If the key contains non-numeric values or is not
defined nothing is done. Returns `1` if successful, `<empty string>` if not.
Examples:

    increment mykey
    increment mykey 3600

##### decrement

    decrement <key> <timeout>?

See `increment`. This does the reverse. Returns `1` if successful, `<empty string>`
if not. Examples:

    decrement mykey
    decrement mykey 3600

##### delete

    delete <key>

Delete a cached value. If the key does not exist nothing is done. Returns `1` if
successful, `<empty string>` if not. Examples:

    delete mykey

##### has

    has <key>

Checks whether Caphpe has the key defined. `has` does not check the value at all.
Returns `1` if successful, `<empty string>` if not. Examples:

    has mykey

##### get

    get <key>

Get a value from Caphpe. Returns an empty string if not value is determined for the
key or the key does not exist. Returns the value if if one exists, `<empty string>`
if not. Examples:

    get mykey

##### flush

    flush

Flushes **all data** from a Caphpe instance. Returns `1` if successful, `<empty
string>` if not. Examples:

    flush

##### close

    close
    
Close an open socket connection to Caphpe if using through telnet for instance.
Examples:

    close

## Why Caphpe

Caphpe works where PHP CLI works. This means you can download the PHAR and just run
it without the need to compile software in environments where compilers may not even
be available.

Of course it is not as performant as something like Redis or Memcached, but it can 
provide a simple cache backend for lighter use cases.

## Warning

Caphpe is not yet production ready as-is. Feel free to try it out though.

Caphpe does not persist the cached data anywhere. If the Caphpe instance is killed 
all stored data is gone too.

## TODO

See the issue tracker for things that need to be done or would be nice to have done.
Add issues to request fixes and features.

## License

Caphpe is licensed with the MIT License. Please see `LICENSE.md`.
