# You could use curl and not have to mess with that HTTP stuff. #

In our testing, the curl library was very capable.  However, it was also slower than simply using fsockopen and talking minimum HTTP protocol.

# What about other cache storage? #

The focus of the original project was a memcached based caching proxy.  If others would like to alter the code to support xcache, apc or others, you are welcome to.  However, in the interest of speed, this was not abstracted.  Abstractions slows down code.

# Can't you do this with perlbal or nginx? #

You can serve files directly from memcached with perlbal or nginx.  However, that data must be populated by some other method.  Also, caching interaction is not handled by those methods.  MemProxy tells the client how long an item should be cached based on its ttl provided by the application server.  It responds to If-Modified-Since headers appropriately.