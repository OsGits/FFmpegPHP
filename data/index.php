<?php
// 目录保护脚本 - 禁止直接访问配置目录
// 如果直接访问此目录，返回404错误

http_response_code(404);
exit('Not Found');
