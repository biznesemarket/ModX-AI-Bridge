import { BridgeClient, McpClient } from '../../sdk/typescript/src/index.js';
const bridge=new BridgeClient({baseUrl:process.env.AIBRIDGE_URL!,token:process.env.AIBRIDGE_TOKEN!});
console.log(await bridge.siteFingerprint());
const mcp=new McpClient(bridge); console.log(await mcp.tools());
