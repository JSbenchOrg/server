INSERT INTO `browsers` VALUES (1,'Chrome','50.0.2661.94');
INSERT INTO `entries` VALUES (1,1,'test','/Hell/i.test(str);'),(2,1,'search','str.search(q) > -1;'),(3,1,'match','str.match(q).length > 0;');
INSERT INTO `os` VALUES (1,'32','Windows','10.0');
INSERT INTO `revisions` VALUES (1,1,1,0,'My test case','auto-generated-slug','This is a description','','var str = \'Hello, world.\',\n        q = \'Hell\',\n        l = q.length,\n        re = /Hell/i;','');
INSERT INTO `testcases` VALUES (1,'auto-generated-slug','public',1);
INSERT INTO `totals` VALUES (1,1,1,1,'opsPerSec','50',1),(2,2,1,1,'opsPerSec','111',1),(3,3,1,1,'opsPerSec','100',1);

