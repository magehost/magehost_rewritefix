## JeroenVermeulen_RewriteFix

**We are sorry but we cannot offer customer support for this extension, and it is provided "as-is" for free. We use it at a number of big shops and it works well.**

Due to bugs in Magento, once an rewrite URL ends with -[number] you get more and more rewrite URLs to the same target. The number gets higher and higher. Indexing gets slower and slower.

This extension is a workaround for this problem.

Requires Magento 1.7.0.2 or greater.

You can verify if your installation has this problem by using this query (presuming no DB prefix). If the largest count is > the number of stores then this is a hint that something is wrong. 

```
SELECT id_path, count(*) as total
FROM `core_url_rewrite`
group by id_path
order by total desc
LIMIT 50
```

Also you can open the id_path with the highest count `select * where id_path = [ID from previous query]`

There should be 1 entry per id_path for every store view - and not more - unless you renamed the produuct URI yourself. 


