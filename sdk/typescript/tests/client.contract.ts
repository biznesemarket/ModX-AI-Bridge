import { BridgeClient } from '../src/client.js';
const fake=async (_input:RequestInfo|URL,_init?:RequestInit)=>new Response(JSON.stringify({ok:true}),{status:200,headers:{'content-type':'application/json'}});
const client=new BridgeClient({baseUrl:'https://bridge.example',token:'test',fetchImpl:fake});
void client.createResource({pagetitle:'Contract test'},'idem-test');
