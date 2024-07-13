# Performance Considerations

## Is PHP slow? 

Should PHP be slow? The Swoole extension, written in C++, should work faster, right?...

Synthetic performance tests, focused on the number of successfully handled `HTTP` connections, 
show that in a simple scenario with `1` Worker process plus a basic `GET` request, 
there is no difference between the Swoole and `AMPHP` servers.

```bash
C:\server\JMeter>hey -n 5000 -c 1 -disable-keepalive http://swoole

Summary:
  Total:        3.6237 secs
  Slowest:      0.0058 secs
  Fastest:      0.0007 secs
  Average:      0.0007 secs
  Requests/sec: 1379.8157

  Total data:   60000 bytes
  Size/request: 12 bytes

Response time histogram:
  0.001 [1]     |
  0.001 [4986]  |■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■
  0.002 [10]    |
  0.002 [0]     |
  0.003 [2]     |
  0.003 [0]     |
  0.004 [0]     |
  0.004 [0]     |
  0.005 [0]     |
  0.005 [0]     |
  0.006 [1]     |
```

```bash
C:\server\JMeter>hey -n 5000 -c 1 -disable-keepalive http://amphp/

Summary:
  Total:        3.9392 secs
  Slowest:      0.0056 secs
  Fastest:      0.0007 secs
  Average:      0.0008 secs
  Requests/sec: 1269.3042


Response time histogram:
  0.001 [1]     |
  0.001 [4987]  |■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■
  0.002 [10]    |
  0.002 [1]     |
  0.003 [0]     |
  0.003 [0]     |
  0.004 [0]     |
  0.004 [0]     |
  0.005 [0]     |
  0.005 [0]     |
  0.006 [1]     |
```

The performance difference is predictably unchanged when using multiple Workers. 
Of course, `C++` processes `HTTP` requests faster than `PHP`, but when the request is simple, 
the difference is only fractions of a percent.

The performance difference becomes noticeable under high concurrency and a large number of connections,
given that the tested code has no business logic.
You can see how `Swoole` confidently pulls ahead.
This difference becomes more pronounced as the number of simultaneous connections increases.

```bash
~/worker$ wrk -t4 -c10 -d30s http://amphp
Running 30s test @ http://amphp
  4 threads and 10 connections
  Thread Stats   Avg      Stdev     Max   +/- Stdev
    Latency   138.15us  130.39us   3.63ms   92.12%
    Req/Sec    15.97k     2.77k   21.79k    66.11%
  1913000 requests in 30.10s, 421.43MB read

Requests/sec:  63555.23

Transfer/sec:     14.00MB

====================================================

~/worker$ wrk -t4 -c10 -d30s http://swoole
Running 30s test @ http://swoole
  4 threads and 10 connections
  Thread Stats   Avg      Stdev     Max   +/- Stdev
    Latency    93.29us   12.32us 586.00us   74.02%
    Req/Sec    20.91k   228.20    21.59k    74.17%
  2504705 requests in 30.10s, 329.64MB read

Requests/sec:  83213.14

Transfer/sec:     10.95MB
```

However, it will remain significant only if the main code "does nothing."

What does this mean? 
It means that in real-world scenarios, this difference may narrow down to just a few percent. 
Low latency matters for certain scenarios and can be critical for APIs that require a high degree of responsiveness, 
but it also implies high-speed processing of business logic. 

If your project uses a database and complex `SQL` queries, such performance gains become simply useless.