CREATE TABLE IF NOT EXISTS `info` (
`id` int(11) NOT NULL AUTO_INCREMENT,
`date` datetime NOT NULL,
`fs_id` char(10) NOT NULL,
`sum` float NOT NULL,
PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=0 ;