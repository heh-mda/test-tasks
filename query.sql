SELECT `article`, `dealer`, `price` FROM `shop` shop1 WHERE price = (SELECT MAX(`price`) FROM `shop` shop2 WHERE shop2.article = shop1.article) ORDER BY `article`